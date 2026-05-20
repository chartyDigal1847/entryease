<?php

namespace App\DTOs\Deoris;

final class ExamEventData
{
    public function __construct(
        public readonly int $applicantId,
        public readonly int $studentId,
        public readonly string $studentEmail,
        public readonly string $studentName,
        public readonly int $examScheduleId,
        public readonly ?string $scheduleTitle,
        public readonly ?float $score = null,
        public readonly ?float $totalItems = null,
        public readonly ?float $percentage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'applicant_id' => $this->applicantId,
            'student_id' => $this->studentId,
            'student_email' => $this->studentEmail,
            'student_name' => $this->studentName,
            'exam_schedule_id' => $this->examScheduleId,
            'schedule_title' => $this->scheduleTitle,
            'score' => $this->score,
            'total_items' => $this->totalItems,
            'percentage' => $this->percentage,
        ], fn ($value) => $value !== null);
    }
}
