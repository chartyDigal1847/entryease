<?php

namespace App\Services\Admission;

use App\DTOs\Deoris\ApplicationStatusChangedData;
use App\DTOs\Deoris\ApplicationSubmittedData;
use App\DTOs\Deoris\ExamEventData;
use App\Events\Deoris\AdmissionApproved;
use App\Events\Deoris\AdmissionRejected;
use App\Events\Deoris\ApplicationStatusChanged;
use App\Events\Deoris\ApplicationSubmitted;
use App\Events\Deoris\ExamAssigned;
use App\Events\Deoris\ExamScoreReleased;
use App\Models\Applicant;
use App\Models\ExamSchedule;
use App\Models\ExamScore;
use App\Services\DeorisUserService;
use App\Services\Integration\DeorisEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdmissionEventService
{
    public function __construct(
        private readonly DeorisEventDispatcher $dispatcher,
        private readonly DeorisUserService $deorisUserService,
    ) {}

    private function getDeorisUserData(?int $deorisUserId): array
    {
        if (!$deorisUserId) {
            return ['email' => null, 'name' => null];
        }

        try {
            $user = DB::connection('deoris')
                ->table('users')
                ->where('id', $deorisUserId)
                ->first();

            return [
                'email' => $user?->email,
                'name' => $user?->name,
            ];
        } catch (\Exception $e) {
            return ['email' => null, 'name' => null];
        }
    }

    public function applicationSubmitted(Applicant $applicant, ?string $correlationId = null): void
    {
        $userData = $this->getDeorisUserData($applicant->deoris_user_id);

        $data = new ApplicationSubmittedData(
            applicantId: $applicant->id,
            studentId: $applicant->deoris_user_id,
            studentEmail: (string) $userData['email'],
            studentName: (string) $userData['name'],
            gradeLevel: (string) $applicant->grade_level,
            status: (string) $applicant->status,
        );

        $this->dispatcher->dispatch(
            new ApplicationSubmitted($data, $correlationId ?? (string) Str::uuid()),
        );
    }

    public function statusChanged(
        Applicant $applicant,
        string $previousStatus,
        ?string $reviewedBy = null,
        ?string $correlationId = null,
    ): void {
        $userData = $this->getDeorisUserData($applicant->deoris_user_id);

        $data = new ApplicationStatusChangedData(
            applicantId: $applicant->id,
            studentId: $applicant->deoris_user_id,
            studentEmail: (string) $userData['email'],
            studentName: (string) $userData['name'],
            previousStatus: $previousStatus,
            newStatus: (string) $applicant->status,
            admissionStatus: (string) $applicant->admission_status,
            reviewedBy: $reviewedBy,
        );

        $correlationId ??= (string) Str::uuid();

        // Avoid double portal notifications: terminal decisions use dedicated events only.
        if ($applicant->status === 'Approved') {
            $this->dispatcher->dispatch(new AdmissionApproved($data, $correlationId));

            return;
        }

        if ($applicant->status === 'Rejected') {
            $this->dispatcher->dispatch(new AdmissionRejected($data, $correlationId));

            return;
        }

        $this->dispatcher->dispatch(
            new ApplicationStatusChanged($data, $correlationId),
        );
    }

    public function examAssigned(Applicant $applicant, ExamSchedule $schedule, ?string $correlationId = null): void
    {
        $userData = $this->getDeorisUserData($applicant->deoris_user_id);

        $data = new ExamEventData(
            applicantId: $applicant->id,
            studentId: $applicant->deoris_user_id,
            studentEmail: (string) $userData['email'],
            studentName: (string) $userData['name'],
            examScheduleId: $schedule->id,
            scheduleTitle: $schedule->title,
        );

        $this->dispatcher->dispatch(new ExamAssigned($data, $correlationId ?? (string) Str::uuid()));
    }

    public function examCompleted(Applicant $applicant, ExamScore $score, ?string $correlationId = null): void
    {
        $applicant->loadMissing('examSchedule');
        $percentage = $score->total_items > 0
            ? round(($score->score / $score->total_items) * 100, 1)
            : null;

        $userData = $this->getDeorisUserData($applicant->deoris_user_id);

        $data = new ExamEventData(
            applicantId: $applicant->id,
            studentId: $applicant->deoris_user_id,
            studentEmail: (string) $userData['email'],
            studentName: (string) $userData['name'],
            examScheduleId: $score->exam_schedule_id,
            scheduleTitle: $applicant->examSchedule?->title,
            score: (float) $score->score,
            totalItems: (float) $score->total_items,
            percentage: $percentage,
        );

        $correlationId ??= (string) Str::uuid();

        $this->dispatcher->dispatch(new ExamScoreReleased($data, $correlationId));
    }
}
