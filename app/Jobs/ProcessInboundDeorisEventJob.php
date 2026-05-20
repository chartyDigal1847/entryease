<?php

namespace App\Jobs;

use App\DTOs\Deoris\DeorisEventEnvelope;
use App\Services\Integration\EventBusConsumer;
use App\Services\Integration\InboundEventDispatcher;
use App\Services\Integration\ReplayProtectionService;
use App\Services\Integration\TrustedModuleValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInboundDeorisEventJob implements ShouldQueue
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
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
        $this->onConnection(config('queue.default'));
        $this->onQueue(config('deoris.redis_queue', 'deoris-events'));
    }

    public function handle(
        TrustedModuleValidator $trustedModules,
        ReplayProtectionService $replayProtection,
        InboundEventDispatcher $dispatcher,
    ): void {
        $envelope = DeorisEventEnvelope::fromArray($this->payload);

        $trustedModules->validate($envelope);
        $replayProtection->assertFresh($envelope);
        $replayProtection->assertNotProcessed($envelope);

        $dispatcher->dispatch($envelope);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessInboundDeorisEventJob failed', [
            'event' => $this->payload['event'] ?? 'unknown',
            'event_id' => $this->payload['event_id'] ?? null,
            'error' => $exception?->getMessage(),
        ]);
    }
}
