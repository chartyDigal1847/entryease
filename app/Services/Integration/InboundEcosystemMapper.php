<?php

namespace App\Services\Integration;

use App\DTOs\Deoris\DeorisEventEnvelope;
use Deoris\Integration\DTO\EcosystemEvent;

class InboundEcosystemMapper
{
    public function toEnvelope(EcosystemEvent $event): DeorisEventEnvelope
    {
        return new DeorisEventEnvelope(
            event: $event->name,
            version: $event->schemaVersion,
            source: $event->sourceModule,
            timestamp: $event->occurredAt,
            correlationId: $event->correlationId,
            eventId: $event->id,
            data: $event->payload,
        );
    }
}
