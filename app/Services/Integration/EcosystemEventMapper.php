<?php

namespace App\Services\Integration;

use App\Contracts\Deoris\DeorisEventContract;
use Deoris\Integration\DTO\EcosystemEvent;

class EcosystemEventMapper
{
    public function toEcosystemEvent(DeorisEventContract $event): EcosystemEvent
    {
        $data = $event->eventData();

        return EcosystemEvent::make(
            name: $event->eventName(),
            sourceModule: $event->sourceModule(),
            payload: $this->portalPayload($event->eventName(), $data),
            correlationId: $event->correlationId(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function portalPayload(string $eventName, array $data): array
    {
        $base = [
            'student_email' => $data['student_email'] ?? null,
            'student_name' => $data['student_name'] ?? null,
            'applicant_id' => $data['applicant_id'] ?? null,
            'student_id' => $data['student_id'] ?? null,
            'grade_level' => $data['grade_level'] ?? null,
        ];

        return match ($eventName) {
            'ApplicationSubmitted' => array_filter([
                ...$base,
                'status' => $data['status'] ?? 'Pending',
                'notify_staff' => true,
            ], fn ($v) => $v !== null),
            'ApplicationStatusChanged', 'AdmissionApproved', 'AdmissionRejected' => array_filter([
                ...$base,
                'previous_status' => $data['previous_status'] ?? null,
                'new_status' => $data['new_status'] ?? null,
                'admission_status' => $data['admission_status'] ?? null,
                'reviewed_by' => $data['reviewed_by'] ?? null,
            ], fn ($v) => $v !== null),
            'ExamAssigned', 'ExamCompleted', 'ExamScoreReleased' => array_filter([
                ...$base,
                'exam_schedule_id' => $data['exam_schedule_id'] ?? null,
                'schedule_title' => $data['schedule_title'] ?? null,
                'score' => $data['score'] ?? null,
                'total_items' => $data['total_items'] ?? null,
                'percentage' => $data['percentage'] ?? null,
            ], fn ($v) => $v !== null),
            default => array_filter($data, fn ($v) => $v !== null),
        };
    }
}
