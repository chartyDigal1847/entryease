<?php

namespace App\Jobs;

use App\Services\Integration\PortalEcosystemPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishPortalEcosystemEventJob implements ShouldQueue
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

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function __construct(
        public readonly array $eventPayload,
    ) {
        $this->onConnection(config('queue.default'));
        $this->onQueue(config('deoris.portal.queue', 'deoris-events'));
    }

    public function handle(PortalEcosystemPublisher $publisher): void
    {
        $publisher->publishFromArray($this->eventPayload);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('PublishPortalEcosystemEventJob failed', [
            'event' => $this->eventPayload['name'] ?? 'unknown',
            'event_id' => $this->eventPayload['id'] ?? null,
            'error' => $exception?->getMessage(),
        ]);
    }
}
