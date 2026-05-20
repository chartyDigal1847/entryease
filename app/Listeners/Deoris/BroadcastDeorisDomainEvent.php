<?php

namespace App\Listeners\Deoris;

use App\Contracts\Deoris\DeorisEventContract;
use App\Events\Deoris\DeorisRealtimeBroadcast;
use App\Support\DeorisBroadcast;
use Illuminate\Support\Facades\Log;

class BroadcastDeorisDomainEvent
{
    public function handle(DeorisEventContract $event): void
    {
        if (! DeorisBroadcast::isEnabled()) {
            return;
        }

        try {
            broadcast(new DeorisRealtimeBroadcast($event));
        } catch (\Throwable $exception) {
            Log::warning('EntryEase realtime broadcast skipped', [
                'event' => $event->eventName(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
