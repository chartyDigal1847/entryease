<?php

namespace App\Services\Integration;

use App\Contracts\Deoris\DeorisEventContract;
use App\Events\Deoris\AdmissionApproved;
use App\Events\Deoris\AdmissionRejected;
use App\Events\Deoris\ApplicationStatusChanged;
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

        $this->portalPublisher->publish($event, ! $this->requiresImmediatePortalSync($event));

        if (config('deoris.module_bus_enabled', false)) {
            $this->publisher->publish($event);
        }
    }

    private function requiresImmediatePortalSync(DeorisEventContract $event): bool
    {
        return $event instanceof ApplicationStatusChanged
            || $event instanceof AdmissionApproved
            || $event instanceof AdmissionRejected;
    }
}
