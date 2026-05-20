<?php

namespace App\Console\Commands;

use App\Models\DeorisProcessedEvent;
use App\Models\DeorisEventOutbox;
use Illuminate\Console\Command;

/**
 * Prune old processed events and published outbox entries.
 *
 * Keeps the event tables lean by removing records older than --days.
 */
class DeorisPruneEventsCommand extends Command
{
    protected $signature = 'deoris:prune-events
                            {--days=30 : Delete records older than this many days}
                            {--dry-run : Show counts without deleting}';

    protected $description = 'Prune old DEORIS processed events and published outbox entries';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $processedCount = DeorisProcessedEvent::query()
            ->where('processed_at', '<', $cutoff)
            ->count();

        $outboxCount = DeorisEventOutbox::query()
            ->where('status', 'published')
            ->where('published_at', '<', $cutoff)
            ->count();

        $this->info("Processed events older than {$days} days: {$processedCount}");
        $this->info("Published outbox entries older than {$days} days: {$outboxCount}");

        if ($dryRun) {
            $this->warn('Dry run — nothing deleted.');
            return self::SUCCESS;
        }

        DeorisProcessedEvent::query()
            ->where('processed_at', '<', $cutoff)
            ->delete();

        DeorisEventOutbox::query()
            ->where('status', 'published')
            ->where('published_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$processedCount} processed events and {$outboxCount} outbox entries.");

        return self::SUCCESS;
    }
}
