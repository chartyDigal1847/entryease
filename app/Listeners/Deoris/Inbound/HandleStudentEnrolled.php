<?php

namespace App\Listeners\Deoris\Inbound;

use App\Contracts\Deoris\DeorisInboundEventHandler;
use App\DTOs\Deoris\DeorisEventEnvelope;
use App\DTOs\Deoris\Inbound\StudentEnrolledData;
use App\Models\ActivityLog;
use App\Models\Applicant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleStudentEnrolled implements DeorisInboundEventHandler
{
    public function handles(): string
    {
        return 'StudentEnrolled';
    }

    public function handle(DeorisEventEnvelope $envelope): void
    {
        $data = StudentEnrolledData::fromArray($envelope->data);

        // Get DEORIS user ID by email
        $deorisUser = DB::connection('deoris')
            ->table('users')
            ->where('email', $data->studentEmail)
            ->first();

        if ($deorisUser) {
            $applicant = Applicant::query()
                ->where('deoris_user_id', $deorisUser->id)
                ->latest()
                ->first();

            if ($applicant && $applicant->status !== 'Approved') {
                $applicant->update([
                    'status' => 'Approved',
                    'admission_status' => 'approved',
                    'admin_notes' => trim(($applicant->admin_notes ?? '')."\n[StudentEnrolled] Ref: {$data->enrollmentReference}"),
                ]);
            }
        }

        ActivityLog::record(
            "Inbound StudentEnrolled for {$data->studentEmail} ({$data->gradeLevel})",
            'green',
        );

        Log::info('EntryEase processed StudentEnrolled', [
            'correlation_id' => $envelope->correlationId,
        ]);
    }
}
