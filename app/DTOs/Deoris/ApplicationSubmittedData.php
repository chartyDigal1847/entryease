<?php

namespace App\DTOs\Deoris;

final class ApplicationSubmittedData
{
    public function __construct(
        public readonly int $applicantId,
        public readonly int $studentId,
        public readonly string $studentEmail,
        public readonly string $studentName,
        public readonly string $gradeLevel,
        public readonly string $status,
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
            'grade_level' => $this->gradeLevel,
            'status' => $this->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            applicantId: (int) $data['applicant_id'],
            studentId: (int) $data['student_id'],
            studentEmail: (string) $data['student_email'],
            studentName: (string) $data['student_name'],
            gradeLevel: (string) $data['grade_level'],
            status: (string) $data['status'],
        );
    }
}
