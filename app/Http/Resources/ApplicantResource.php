<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Applicant model.
 * Formats applicant data for versioned REST API responses.
 */
class ApplicantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'deoris_user_id'   => $this->deoris_user_id,
            'grade_level'      => $this->grade_level,
            'status'           => $this->effective_status,
            'admission_status' => $this->admission_status,
            'additional_info'  => $this->additional_info
                ? json_decode($this->additional_info, true)
                : null,
            'has_photo'        => !empty($this->photo_2x2),
            'has_psa_cert'     => !empty($this->psa_birth_cert),
            'documents_updated_at' => $this->documents_updated_at?->toIso8601String(),
            'exam_schedule'    => $this->whenLoaded('examSchedule', fn() => [
                'id'        => $this->examSchedule->id,
                'title'     => $this->examSchedule->title,
                'exam_date' => $this->examSchedule->exam_date?->toDateString(),
                'exam_type' => $this->examSchedule->exam_type,
                'venue'     => $this->examSchedule->venue,
                'batch'     => $this->examSchedule->batch,
            ]),
            'exam_score'       => $this->when(
                $this->isAdminOrOfficer($request) && $this->isRelationLoaded('examScore'),
                fn() => $this->examScore ? [
                    'score'       => $this->examScore->score,
                    'total_items' => $this->examScore->total_items,
                    'percentage'  => $this->examScore->total_items > 0
                        ? round(($this->examScore->score / $this->examScore->total_items) * 100, 1)
                        : null,
                    'passed'      => $this->examScore->passed,
                    'recorded_at' => $this->examScore->recorded_at?->toIso8601String(),
                ] : null
            ),
            'exam_completed'   => $this->whenLoaded('examScore', fn() => !is_null($this->examScore)),
            'exam_seat_number' => $this->exam_seat_number,
            'exam_room'        => $this->exam_room,
            'admin_notes'      => $this->when(
                $this->isAdminOrOfficer($request),
                $this->admin_notes
            ),
            'reviewed_by'      => $this->when(
                $this->isAdminOrOfficer($request),
                $this->reviewed_by
            ),
            'submitted_at'     => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }

    private function isAdminOrOfficer(Request $request): bool
    {
        $role = session('sso_role', 'student');
        return in_array($role, ['admin', 'admission_officer', 'registrar', 'hr'], true);
    }
}
