<?php

namespace App\Services\Integration;

use App\DTOs\Deoris\DeorisEventEnvelope;
use App\Jobs\ProcessInboundDeorisEventJob;
use Illuminate\Support\Facades\Log;

class EventBusConsumer
{
    public function __construct(
        private readonly TrustedModuleValidator $trustedModules,
        private readonly ReplayProtectionService $replayProtection,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public function ingest(array $raw, bool $async = true): void
    {
        $envelope = DeorisEventEnvelope::fromArray($raw);

        $this->trustedModules->validate($envelope);
        $this->replayProtection->assertFresh($envelope);
        $this->replayProtection->assertNotProcessed($envelope);

        if ($async) {
            ProcessInboundDeorisEventJob::dispatch($raw)
                ->onConnection(config('queue.default'))
                ->onQueue(config('deoris.redis_queue', 'deoris-events'));
        } else {
            app(InboundEventDispatcher::class)->dispatch($envelope);
        }
    }

    public function handleRawMessage(string $message): void
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
            $this->ingest($decoded);
        } catch (\Throwable $e) {
            Log::channel('stack')->error('DEORIS consumer failed to ingest message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
        }
    }
}
