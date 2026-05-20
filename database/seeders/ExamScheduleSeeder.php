<?php

namespace Database\Seeders;

use App\Models\ExamSchedule;
use Illuminate\Database\Seeder;

class ExamScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = [
            [
                'title'        => 'Grade 7 Entrance Exam – Batch A (Online)',
                'exam_date'    => now()->addDays(14)->toDateString(),
                'start_time'   => '08:00',
                'end_time'     => '10:00',
                'venue'        => null,
                'batch'        => 'Batch A',
                'slots'        => 50,
                'instructions' => 'This is an online exam. Ensure a stable internet connection. You have 2 hours to complete all questions.',
                'status'       => 'upcoming',
                'exam_type'    => 'online',
            ],
            [
                'title'        => 'Grade 7 Entrance Exam – Batch B (On-site)',
                'exam_date'    => now()->addDays(21)->toDateString(),
                'start_time'   => '09:00',
                'end_time'     => '11:00',
                'venue'        => 'Main Campus – Room 101',
                'batch'        => 'Batch B',
                'slots'        => 40,
                'instructions' => 'Report to the venue 30 minutes before the exam. Bring your exam permit and a valid ID.',
                'status'       => 'upcoming',
                'exam_type'    => 'onsite',
            ],
            [
                'title'        => 'Grade 7 Entrance Exam – Batch C (On-site)',
                'exam_date'    => now()->addDays(28)->toDateString(),
                'start_time'   => '13:00',
                'end_time'     => '15:00',
                'venue'        => 'Main Campus – Room 102',
                'batch'        => 'Batch C',
                'slots'        => 40,
                'instructions' => 'Afternoon session. Bring your exam permit and a valid ID.',
                'status'       => 'upcoming',
                'exam_type'    => 'onsite',
            ],
        ];

        foreach ($schedules as $schedule) {
            ExamSchedule::firstOrCreate(
                ['title' => $schedule['title']],
                $schedule
            );
        }

        $this->command->info('Exam schedules seeded.');
    }
}
