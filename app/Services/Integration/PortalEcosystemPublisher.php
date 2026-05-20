<?php

namespace App\Services\Integration;

use App\Contracts\Deoris\DeorisEventContract;
use App\Jobs\PublishPortalEcosystemEventJob;
use Deoris\Integration\DTO\EcosystemEvent;
use Deoris\Integration\EventPublisher;
use Illuminate\Support\Facades\Log;
use Throwable;

class PortalEcosystemPublisher
{
    public function __construct(
        private readonly EcosystemEventMapper $mapper,
        private readonly ?EventPublisher $publisher = null,
    ) {}

    public function publish(DeorisEventContract $event, bool $async = true): ?EcosystemEvent
    {
        if (! config('deoris.portal.publish_enabled', true)) {
            return null;
        }

        $secret = config('deoris.portal.event_secret');

        if (! is_string($secret) || $secret === '') {
            Log::warning('Portal ecosystem publish skipped: ENTRYEASE_EVENT_SECRET is not set.');

            return null;
        }

        $ecosystemEvent = $this->mapper->toEcosystemEvent($event);

        if ($async && config('queue.default') !== 'sync') {
            PublishPortalEcosystemEventJob::dispatch($ecosystemEvent->toArray());

            return $ecosystemEvent;
        }

        $this->publishEcosystemEvent($ecosystemEvent);

        return $ecosystemEvent;
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function publishFromArray(array $eventPayload): void
    {
        $this->publishEcosystemEvent(EcosystemEvent::fromArray($eventPayload));
    }

    private function publishEcosystemEvent(EcosystemEvent $ecosystemEvent): void
    {
        $publisher = $this->publisher();

        if ($publisher === null) {
            return;
        }

        $publisher->publishHttp($ecosystemEvent);

        if (! config('deoris.portal.publish_redis', false)) {
            return;
        }

        try {
            $publisher->publishRedis($ecosystemEvent);
        } catch (Throwable $exception) {
            Log::debug('Portal Redis publish failed after HTTP ingest', [
                'event' => $ecosystemEvent->name,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function publisher(): ?EventPublisher
    {
        if ($this->publisher !== null) {
            return $this->publisher;
        }

        $secret = config('deoris.portal.event_secret');

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        // Pass only the base portal URL - EventPublisher will append the path
        $portalUrl = (string) config('deoris.portal.url', config('app.portal_url'));

        return new EventPublisher(
            portalUrl: $portalUrl,
            moduleSecret: $secret,
            redisChannel: (string) config('deoris.portal.redis_channel', 'deoris.events'),
        );
    }
}
