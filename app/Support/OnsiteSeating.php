<?php

namespace App\Support;

use App\Models\Applicant;
use App\Models\ExamSchedule;

class OnsiteSeating
{
    public static function assign(
        Applicant $applicant,
        ExamSchedule $schedule,
        ?string $room = null,
        ?string $seat = null,
    ): void {
        $room = self::normalize($room) ?: self::normalize($schedule->venue);
        $seat = self::normalize($seat) ?: self::nextSeatNumber($schedule);

        $applicant->update([
            'exam_schedule_id' => $schedule->id,
            'exam_room' => $room,
            'exam_seat_number' => $seat,
        ]);
    }

    public static function update(Applicant $applicant, ?string $room, ?string $seat): void
    {
        $payload = [];

        if ($room !== null) {
            $payload['exam_room'] = self::normalize($room) ?: null;
        }

        if ($seat !== null) {
            $payload['exam_seat_number'] = self::normalize($seat) ?: null;
        }

        if ($payload !== []) {
            $applicant->update($payload);
        }
    }

    public static function clear(Applicant $applicant): void
    {
        $applicant->update([
            'exam_schedule_id' => null,
            'exam_room' => null,
            'exam_seat_number' => null,
        ]);
    }

    public static function nextSeatNumber(ExamSchedule $schedule, ?Applicant $excluding = null): string
    {
        $max = 0;

        $query = $schedule->applicants()->whereNotNull('exam_seat_number');
        if ($excluding) {
            $query->where('id', '!=', $excluding->id);
        }

        foreach ($query->pluck('exam_seat_number') as $value) {
            if (preg_match('/(\d+)/', (string) $value, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return (string) ($max + 1);
    }

    private static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
