<?php

namespace App\Services\Integration;

use App\Contracts\Deoris\DeorisEventContract;
use App\DTOs\Deoris\DeorisEventEnvelope;
use App\Jobs\PublishDeorisEventJob;
use App\Models\DeorisEventOutbox;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class EventBusPublisher
{
    public function __construct(
        private readonly EventSignatureService $signatures,
    ) {}

    public function publish(DeorisEventContract $event, bool $async = true): DeorisEventEnvelope
    {
        $envelope = $this->buildEnvelope($event);

        DeorisEventOutbox::query()->create([
            'event_id' => $envelope->eventId,
            'event' => $envelope->event,
            'status' => 'pending',
            'payload' => $envelope->toArray($this->signatures->sign($envelope)),
        ]);

        if ($async) {
            PublishDeorisEventJob::dispatch($envelope->eventId)
                ->onConnection(config('queue.default'))
                ->onQueue(config('deoris.redis_queue', 'deoris-events'));
        } else {
            $this->publishEnvelope($envelope);
        }

        return $envelope;
    }

    public function publishEnvelope(DeorisEventEnvelope $envelope): void
    {
        $signed = $envelope->toArray($this->signatures->sign($envelope));
        $payload = json_encode($signed, JSON_THROW_ON_ERROR);

        $connection = config('deoris.redis_connection', 'default');
        $channel = config('deoris.redis_channel', 'deoris:events');

        Redis::connection($connection)->publish($channel, $payload);

        DeorisEventOutbox::query()
            ->where('event_id', $envelope->eventId)
            ->first()
            ?->markPublished();
    }

    public function publishByEventId(string $eventId): void
    {
        $outbox = DeorisEventOutbox::query()->where('event_id', $eventId)->firstOrFail();
        $payload = $outbox->payload;
        $envelope = DeorisEventEnvelope::fromArray($payload);

        $this->publishEnvelope($envelope);
    }

    public function buildEnvelope(DeorisEventContract $event): DeorisEventEnvelope
    {
        return new DeorisEventEnvelope(
            event: $event->eventName(),
            version: $event->eventVersion(),
            source: $event->sourceModule(),
            timestamp: now()->toIso8601String(),
            correlationId: $event->correlationId() ?? (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            data: $event->eventData(),
        );
    }
}
