<?php

namespace App\Services\Integration;

use App\Contracts\Deoris\DeorisInboundEventHandler;
use App\DTOs\Deoris\DeorisEventEnvelope;
use App\Models\DeorisProcessedEvent;
use Illuminate\Support\Facades\Log;

class InboundEventDispatcher
{
    /** @var array<string, DeorisInboundEventHandler> */
    private array $handlers = [];

    public function register(DeorisInboundEventHandler $handler): void
    {
        $this->handlers[$handler->handles()] = $handler;
    }

    public function dispatch(DeorisEventEnvelope $envelope): void
    {
        $handler = $this->handlers[$envelope->event] ?? null;

        if ($handler === null) {
            Log::info('DEORIS inbound event ignored (no handler)', [
                'event' => $envelope->event,
                'source' => $envelope->source,
            ]);

            DeorisProcessedEvent::markProcessed(
                $envelope->eventId,
                $envelope->event,
                $envelope->source,
                $envelope->correlationId,
                ['ignored' => true],
            );

            return;
        }

        $handler->handle($envelope);

        DeorisProcessedEvent::markProcessed(
            $envelope->eventId,
            $envelope->event,
            $envelope->source,
            $envelope->correlationId,
        );
    }
}
