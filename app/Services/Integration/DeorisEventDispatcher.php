<?php

namespace App\Services\Integration;

use App\Contracts\Deoris\DeorisEventContract;
use Illuminate\Support\Facades\Event;

/**
 * Dispatches local Laravel domain events and publishes to the DEORIS bus.
 */
class DeorisEventDispatcher
{
    public function __construct(
        private readonly EventBusPublisher $publisher,
        private readonly PortalEcosystemPublisher $portalPublisher,
    ) {}

    public function dispatch(DeorisEventContract $event, bool $publishToBus = true): void
    {
        event($event);

        if (! $publishToBus) {
            return;
        }

        $this->portalPublisher->publish($event);

        if (config('deoris.module_bus_enabled', false)) {
            $this->publisher->publish($event);
        }
    }
}
