<?php

namespace App\DTOs\Deoris;

final class ApplicationStatusChangedData
{
    public function __construct(
        public readonly int $applicantId,
        public readonly int $studentId,
        public readonly string $studentEmail,
        public readonly string $studentName,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly string $admissionStatus,
        public readonly ?string $reviewedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'applicant_id' => $this->applicantId,
            'student_id' => $this->studentId,
            'student_email' => $this->studentEmail,
            'student_name' => $this->studentName,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'admission_status' => $this->admissionStatus,
            'reviewed_by' => $this->reviewedBy,
        ];
    }
}
