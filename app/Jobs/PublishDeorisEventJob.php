<?php

namespace App\Jobs;

use App\Models\DeorisEventOutbox;
use App\Services\Integration\EventBusPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishDeorisEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 45, 120, 300];
    }

    public function __construct(
        public readonly string $eventId,
    ) {
        $this->onConnection(config('queue.default'));
        $this->onQueue(config('deoris.redis_queue', 'deoris-events'));
    }

    public function handle(EventBusPublisher $publisher): void
    {
        $publisher->publishByEventId($this->eventId);
    }

    public function failed(?Throwable $exception): void
    {
        DeorisEventOutbox::query()
            ->where('event_id', $this->eventId)
            ->first()
            ?->markFailed($exception?->getMessage() ?? 'Unknown error');

        Log::error('PublishDeorisEventJob failed', [
            'event_id' => $this->eventId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
