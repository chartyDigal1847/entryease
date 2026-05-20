<?php

namespace App\DTOs\Deoris;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DeorisEventEnvelope
{
    public function __construct(
        public readonly string $event,
        public readonly string $version,
        public readonly string $source,
        public readonly string $timestamp,
        public readonly string $correlationId,
        public readonly string $eventId,
        public readonly array $data,
        public readonly ?string $signature = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $validated = Validator::make($payload, [
            'event' => 'required|string|max:100',
            'version' => 'required|string|max:10',
            'source' => 'required|string|max:50',
            'timestamp' => 'required|date',
            'correlation_id' => 'required|string|max:64',
            'event_id' => 'required|uuid',
            'data' => 'required|array',
            'signature' => 'nullable|string',
        ])->validate();

        return new self(
            event: $validated['event'],
            version: $validated['version'],
            source: $validated['source'],
            timestamp: (string) $validated['timestamp'],
            correlationId: $validated['correlation_id'],
            eventId: $validated['event_id'],
            data: $validated['data'],
            signature: $validated['signature'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $signature = null): array
    {
        return [
            'event' => $this->event,
            'version' => $this->version,
            'source' => $this->source,
            'timestamp' => $this->timestamp,
            'correlation_id' => $this->correlationId,
            'event_id' => $this->eventId,
            'data' => $this->data,
            'signature' => $signature ?? $this->signature,
        ];
    }

    /**
     * Payload used for HMAC signing (signature field excluded).
     *
     * @return array<string, mixed>
     */
    public function signingPayload(): array
    {
        return Arr::except($this->toArray(), ['signature']);
    }
}
