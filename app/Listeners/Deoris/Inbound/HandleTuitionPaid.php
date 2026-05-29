<?php

namespace App\Listeners\Deoris\Inbound;

use App\Contracts\Deoris\DeorisInboundEventHandler;
use App\DTOs\Deoris\DeorisEventEnvelope;
use App\DTOs\Deoris\Inbound\TuitionPaidData;
use App\Models\ActivityLog;
use App\Models\Applicant;
use Illuminate\Support\Facades\Log;

class HandleTuitionPaid implements DeorisInboundEventHandler
{
    public function handles(): string
    {
        return 'TuitionPaid';
    }

    public function handle(DeorisEventEnvelope $envelope): void
    {
        $data = TuitionPaidData::fromArray($envelope->data);

        $applicant = Applicant::query()
            ->where(function ($q) use ($data) {
                $q->where('portal_student_email', $data->studentEmail);
                if ($data->studentExternalId) {
                    $q->orWhere('deoris_user_id', $data->studentExternalId);
                }
            })
            ->latest()
            ->first();

        if ($applicant) {
            $applicant->update([
                'admin_notes' => trim(($applicant->admin_notes ?? '')."\n[TuitionPaid] Ref: {$data->paymentReference}"),
            ]);
        }

        ActivityLog::record(
            "Inbound TuitionPaid for {$data->studentEmail} (ref {$data->paymentReference})",
            'green',
        );

        Log::info('EntryEase processed TuitionPaid', [
            'correlation_id' => $envelope->correlationId,
            'payment_reference' => $data->paymentReference,
        ]);
    }
}
