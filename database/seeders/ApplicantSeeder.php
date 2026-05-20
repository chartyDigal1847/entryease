<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ActivityLog;
use Illuminate\Database\Seeder;

/**
 * Seeds demo applicants using placeholder DEORIS user IDs.
 *
 * In production, deoris_user_id values come from the DEORIS portal.
 * These seeds use IDs 1–5 as placeholders for local dev/testing.
 */
class ApplicantSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['Pending', 'Under Review', 'Approved', 'Rejected'];

        $applicants = [
            [
                'deoris_user_id'   => 1,
                'grade_level'      => 'Grade 7',
                'status'           => 'Approved',
                'admission_status' => 'approved',
                'additional_info'  => json_encode([
                    'phone'           => '09171234567',
                    'previous_school' => 'Mabini Elementary School',
                ]),
            ],
            [
                'deoris_user_id'   => 2,
                'grade_level'      => 'Grade 7',
                'status'           => 'Under Review',
                'admission_status' => 'under_review',
                'additional_info'  => json_encode([
                    'phone'           => '09281234567',
                    'previous_school' => 'Rizal Elementary School',
                ]),
            ],
            [
                'deoris_user_id'   => 3,
                'grade_level'      => 'Grade 7',
                'status'           => 'Pending',
                'admission_status' => 'pending',
                'additional_info'  => json_encode([
                    'phone'           => '09391234567',
                    'previous_school' => 'Bonifacio Elementary School',
                ]),
            ],
            [
                'deoris_user_id'   => 4,
                'grade_level'      => 'Grade 7',
                'status'           => 'Rejected',
                'admission_status' => 'rejected',
                'additional_info'  => json_encode([
                    'phone'           => '09401234567',
                    'previous_school' => 'Luna Elementary School',
                ]),
            ],
            [
                'deoris_user_id'   => 5,
                'grade_level'      => 'Grade 7',
                'status'           => 'Pending',
                'admission_status' => 'pending',
                'additional_info'  => json_encode([
                    'phone'           => '09501234567',
                    'previous_school' => 'Del Pilar Elementary School',
                ]),
            ],
        ];

        foreach ($applicants as $data) {
            Applicant::firstOrCreate(
                ['deoris_user_id' => $data['deoris_user_id']],
                $data
            );
        }

        ActivityLog::record('Demo applicants seeded for development.', 'blue');

        $this->command->info('Applicants seeded.');
    }
}
