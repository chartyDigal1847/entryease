<?php

namespace App\Services\Integration;

use App\Models\DeorisProcessedEvent;
use Deoris\Integration\DTO\EcosystemEvent;
use Deoris\Integration\Support\SignedEventEnvelope;
use Illuminate\Support\Facades\Log;

class PortalEcosystemConsumer
{
    public function __construct(
        private readonly InboundEcosystemMapper $mapper,
        private readonly InboundEventDispatcher $dispatcher,
    ) {}

    public function handleMessage(string $message): void
    {
        try {
            $event = $this->unwrap($message);
        } catch (\Throwable $exception) {
            Log::warning('Portal ecosystem message rejected', ['error' => $exception->getMessage()]);

            return;
        }

        if ($event->sourceModule === config('deoris.module_name', 'EntryEase')) {
            return;
        }

        if (! in_array($event->name, config('deoris.inbound_events', []), true)) {
            return;
        }

        if (DeorisProcessedEvent::wasProcessed($event->id)) {
            return;
        }

        $this->dispatcher->dispatch($this->mapper->toEnvelope($event));
    }

    private function unwrap(string $message): EcosystemEvent
    {
        $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

        if (isset($decoded['event'], $decoded['module'], $decoded['signature'])) {
            return SignedEventEnvelope::unwrap($message, function (string $module): ?string {
                if ($module === 'Portal') {
                    return config('deoris.module_secrets.Portal');
                }

                return config("deoris.module_secrets.{$module}");
            });
        }

        return EcosystemEvent::fromArray($decoded);
    }
}
