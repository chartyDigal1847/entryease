<?php

namespace App\Listeners\Deoris\Inbound;

use App\Contracts\Deoris\DeorisInboundEventHandler;
use App\DTOs\Deoris\DeorisEventEnvelope;
use App\DTOs\Deoris\Inbound\MedicalApprovedData;
use App\Models\ActivityLog;
use App\Models\Applicant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleMedicalApproved implements DeorisInboundEventHandler
{
    public function handles(): string
    {
        return 'MedicalApproved';
    }

    public function handle(DeorisEventEnvelope $envelope): void
    {
        $data = MedicalApprovedData::fromArray($envelope->data);

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

            if ($applicant) {
                $note = "[MedicalApproved] Ref: {$data->clearanceReference}";
                $applicant->update([
                    'admin_notes' => trim(($applicant->admin_notes ?? '')."\n".$note),
                ]);
            }
        }

        ActivityLog::record(
            "Inbound MedicalApproved for {$data->studentEmail}",
            'green',
        );

        Log::info('EntryEase processed MedicalApproved', [
            'correlation_id' => $envelope->correlationId,
        ]);
    }
}
