<?php

namespace App\Console\Commands;

use App\Jobs\PublishDeorisEventJob;
use App\Models\DeorisEventOutbox;
use Illuminate\Console\Command;

/**
 * Retry stuck outbox events that failed to publish.
 *
 * Picks up events with status = 'pending' or 'failed' and attempts < 5,
 * re-dispatches them to the queue for publishing.
 */
class DeorisRetryOutboxCommand extends Command
{
    protected $signature = 'deoris:retry-outbox
                            {--limit=50 : Maximum events to retry per run}
                            {--dry-run : Show what would be retried without dispatching}';

    protected $description = 'Retry stuck DEORIS outbox events that failed to publish';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $events = DeorisEventOutbox::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 5)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) {
            $this->info('No stuck outbox events found.');
            return self::SUCCESS;
        }

        $this->info("Found {$events->count()} event(s) to retry.");

        foreach ($events as $event) {
            $this->line("  [{$event->status}] {$event->event} ({$event->event_id}) — attempt #{$event->attempts}");

            if (! $dryRun) {
                PublishDeorisEventJob::dispatch($event->event_id)
                    ->onConnection(config('queue.default'))
                    ->onQueue(config('deoris.redis_queue', 'deoris-events'));

                $event->increment('attempts');
            }
        }

        if ($dryRun) {
            $this->warn('Dry run — no events dispatched.');
        } else {
            $this->info("Dispatched {$events->count()} event(s) to queue.");
        }

        return self::SUCCESS;
    }
}
