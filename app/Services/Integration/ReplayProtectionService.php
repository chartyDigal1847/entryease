<?php

namespace App\Services\Integration;

use App\DTOs\Deoris\DeorisEventEnvelope;
use App\Models\DeorisProcessedEvent;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ReplayProtectionService
{
    public function assertFresh(DeorisEventEnvelope $envelope): void
    {
        $maxAge = (int) config('deoris.max_event_age_seconds', 300);
        $timestamp = Carbon::parse($envelope->timestamp);

        if ($timestamp->diffInSeconds(now(), absolute: true) > $maxAge) {
            throw ValidationException::withMessages([
                'timestamp' => ['Event timestamp is outside the allowed window.'],
            ]);
        }
    }

    public function assertNotProcessed(DeorisEventEnvelope $envelope): void
    {
        if (DeorisProcessedEvent::wasProcessed($envelope->eventId)) {
            throw ValidationException::withMessages([
                'event_id' => ['Event has already been processed (duplicate).'],
            ]);
        }
    }
}
