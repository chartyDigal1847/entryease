<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeorisUserService
{
    /**
     * Update the admission status of a user in the DEORIS database by email.
     *
     * @param string $email The email of the user to update
     * @param string $status The new admission status ('approved' or 'rejected')
     * @return bool True if successful, false otherwise
     */
    public function updateAdmissionStatus(string $email, string $status): bool
    {
        try {
            $updated = DB::connection('deoris')
                ->table('users')
                ->where('email', $email)
                ->update(['admission_status' => $status]);

            if ($updated > 0) {
                Log::info('DEORIS user admission status updated', [
                    'email' => $email,
                    'status' => $status,
                ]);
                return true;
            }

            Log::warning('DEORIS user not found for admission status update', [
                'email' => $email,
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to update DEORIS user admission status', [
                'email' => $email,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update the admission status of a user in the DEORIS database by ID.
     *
     * @param int $deorisUserId The DEORIS user ID
     * @param string $status The new admission status ('approved' or 'rejected')
     * @return bool True if successful, false otherwise
     */
    public function updateAdmissionStatusById(int $deorisUserId, string $status): bool
    {
        try {
            $updated = DB::connection('deoris')
                ->table('users')
                ->where('id', $deorisUserId)
                ->update(['admission_status' => $status]);

            if ($updated > 0) {
                Log::info('DEORIS user admission status updated by ID', [
                    'deoris_user_id' => $deorisUserId,
                    'status' => $status,
                ]);
                return true;
            }

            Log::warning('DEORIS user not found for admission status update by ID', [
                'deoris_user_id' => $deorisUserId,
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to update DEORIS user admission status by ID', [
                'deoris_user_id' => $deorisUserId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the admission status of a user from the DEORIS database by email.
     *
     * @param string $email The email of the user
     * @return string|null The admission status or null if user not found
     */
    public function getAdmissionStatus(string $email): ?string
    {
        try {
            $user = DB::connection('deoris')
                ->table('users')
                ->where('email', $email)
                ->first();

            return $user?->admission_status;
        } catch (\Exception $e) {
            Log::error('Failed to get DEORIS user admission status', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get the admission status of a user from the DEORIS database by ID.
     *
     * @param int $deorisUserId The DEORIS user ID
     * @return string|null The admission status or null if user not found
     */
    public function getAdmissionStatusById(int $deorisUserId): ?string
    {
        try {
            $user = DB::connection('deoris')
                ->table('users')
                ->where('id', $deorisUserId)
                ->first();

            return $user?->admission_status;
        } catch (\Exception $e) {
            Log::error('Failed to get DEORIS user admission status by ID', [
                'deoris_user_id' => $deorisUserId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
