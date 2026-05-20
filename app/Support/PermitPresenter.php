<?php

namespace App\Support;

use App\Models\Applicant;
use App\Models\ExamSchedule;

class PermitPresenter
{
    public function __construct(
        public Applicant $application,
        public ExamSchedule $schedule,
        public object $student,
    ) {}

    public static function make(Applicant $application, ExamSchedule $schedule, object $student): self
    {
        return new self($application, $schedule, $student);
    }

    public function permitNumber(): string
    {
        return str_pad((string) $this->application->id, 5, '0', STR_PAD_LEFT);
    }

    public function gradeLabel(): string
    {
        return $this->application->grade_level
            ?? $this->schedule->batch
            ?? 'Grade 7';
    }

    public function timeRange(): string
    {
        if ($this->schedule->start_time && $this->schedule->end_time) {
            return $this->schedule->start_time->format('g:i A')
                . ' – '
                . $this->schedule->end_time->format('g:i A');
        }

        return 'To be announced';
    }

    public function examDateLabel(): string
    {
        return $this->schedule->exam_date
            ? $this->schedule->exam_date->format('l, F d, Y')
            : 'To be announced';
    }

    public function room(): ?string
    {
        $room = $this->application->exam_room;

        return $room && trim($room) !== '' ? trim($room) : null;
    }

    public function seat(): ?string
    {
        return $this->application->exam_seat_number
            ? trim((string) $this->application->exam_seat_number)
            : null;
    }

    public function hasSeating(): bool
    {
        return $this->room() !== null && $this->seat() !== null;
    }

    public function roomDisplay(): string
    {
        return $this->room() ?? 'Pending assignment';
    }

    public function seatDisplay(): string
    {
        return $this->seat() ?? 'Pending assignment';
    }

    public function venue(): ?string
    {
        $venue = $this->schedule->venue;

        return $venue && trim($venue) !== '' ? trim($venue) : null;
    }
}
