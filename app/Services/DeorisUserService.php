<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * @deprecated Identity updates are published via AdmissionEventService → DEORIS event hub.
 *             Do not write to the DEORIS database from EntryEase.
 */
class DeorisUserService
{
    public function updateAdmissionStatus(string $email, string $status): bool
    {
        Log::debug('[DeorisUserService] Skipped direct DB update; use event hub', [
            'email' => $email,
            'status' => $status,
        ]);

        return true;
    }

    public function updateAdmissionStatusById(int $deorisUserId, string $status): bool
    {
        Log::debug('[DeorisUserService] Skipped direct DB update; use event hub', [
            'deoris_user_id' => $deorisUserId,
            'status' => $status,
        ]);

        return true;
    }

    public function getAdmissionStatus(string $email): ?string
    {
        return null;
    }

    public function getAdmissionStatusById(int $deorisUserId): ?string
    {
        return null;
    }
}
