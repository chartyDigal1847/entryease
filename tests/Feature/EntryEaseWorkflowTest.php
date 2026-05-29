<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ExamQuestion;
use App\Models\ExamSchedule;
use App\Models\ExamScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntryEaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_7_exam_workflow_is_database_backed_end_to_end(): void
    {
        config(['deoris.portal.publish_enabled' => false]);
        Storage::fake('private');

        $studentSession = [
            'sso_id' => 101,
            'sso_role' => 'student',
            'sso_name' => 'Pat Student',
            'sso_email' => 'pat.student@example.test',
            'user' => [
                'id' => 101,
                'name' => 'Pat Student',
                'email' => 'pat.student@example.test',
                'role' => 'student',
            ],
        ];

        $this->withSession($studentSession)
            ->post(route('student.apply.store'), [
                'phone' => '09170000001',
                'previous_school' => 'Sample Elementary School',
                'photo_2x2' => UploadedFile::fake()->create('photo.jpg', 128, 'image/jpeg'),
                'psa_birth_cert' => UploadedFile::fake()->create('psa.pdf', 128, 'application/pdf'),
            ])
            ->assertRedirect(route('student.applications'));

        $applicant = Applicant::where('deoris_user_id', 101)->firstOrFail();

        $this->assertSame('Grade 7', $applicant->grade_level);
        $this->assertSame('pending', $applicant->admission_status);

        $officerSession = [
            'sso_role' => 'registrar',
            'sso_name' => 'Officer One',
            'sso_email' => 'officer@example.test',
            'sso_id' => 501,
        ];

        $this->withSession($officerSession)
            ->post('/exam/schedules', [
                'title' => 'Grade 7 Entrance Exam - Batch A',
                'exam_date' => now()->addDay()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'venue' => 'Testing Room 1',
                'exam_type' => 'online',
                'batch' => 'Batch A',
                'slots' => 30,
                'instructions' => 'Answer all questions.',
            ])
            ->assertRedirect(route('exam.schedules'));

        $schedule = ExamSchedule::firstOrFail();

        $this->withSession($officerSession)
            ->post(route('exam.schedules.applicants.assign', $schedule), [
                'applicant_id' => $applicant->id,
            ])
            ->assertRedirect();

        $this->withSession($officerSession)
            ->post(route('exam.schedules.questions.store', $schedule), [
                'question_text' => 'What is 2 + 2?',
                'choice_a' => '4',
                'choice_b' => '3',
                'choice_c' => '2',
                'choice_d' => '1',
                'correct_answer' => 'A',
                'points' => 1,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->withSession($officerSession)
            ->post(route('exam.schedules.questions.store', $schedule), [
                'question_text' => 'Which word means begin?',
                'choice_a' => 'Stop',
                'choice_b' => 'Start',
                'choice_c' => 'Close',
                'choice_d' => 'Finish',
                'correct_answer' => 'B',
                'points' => 1,
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $questions = ExamQuestion::orderBy('sort_order')->get();
        $this->assertCount(2, $questions);

        $this->withSession($studentSession)
            ->get(route('student.exam.take'))
            ->assertOk()
            ->assertSee('Grade 7 Entrance Exam')
            ->assertSee('Submit Exam');

        $this->withSession($studentSession)
            ->post(route('student.exam.submit'), [
                'answers' => [
                    $questions[0]->id => 'A',
                    $questions[1]->id => 'B',
                ],
            ])
            ->assertRedirect(route('student.results'));

        $score = ExamScore::where('applicant_id', $applicant->id)->firstOrFail();
        $this->assertSame(2.0, $score->score);
        $this->assertSame(2.0, $score->total_items);
        $this->assertSame(100.0, $score->percentage);

        $this->withSession($officerSession)
            ->put(route('registrar.application.update', $applicant), [
                'status' => 'Approved',
                'notes' => 'Passed entrance exam.',
            ])
            ->assertRedirect();

        $applicant->refresh();
        $this->assertSame('Approved', $applicant->status);
        $this->assertSame('approved', $applicant->admission_status);
    }

    public function test_access_rules_prevent_admin_and_student_score_or_approval_changes(): void
    {
        $schedule = ExamSchedule::create([
            'title' => 'Grade 7 Entrance Exam - Batch B',
            'exam_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '13:00',
            'end_time' => '14:00',
            'venue' => 'Testing Room 2',
            'batch' => 'Batch B',
            'slots' => 30,
        ]);

        $applicant = Applicant::create([
            'deoris_user_id' => 133,
            'portal_student_name' => 'Restricted Student',
            'portal_student_email' => 'restricted@example.test',
            'grade_level' => 'Grade 7',
            'exam_schedule_id' => $schedule->id,
            'status' => 'Pending',
            'admission_status' => 'pending',
        ]);

        $adminSession = [
            'sso_id' => 1,
            'sso_role' => 'admin',
            'sso_name' => 'Admin',
            'sso_email' => 'admin@example.test',
        ];
        $studentSession = [
            'sso_id' => 133,
            'sso_role' => 'student',
            'sso_name' => 'Restricted Student',
            'sso_email' => 'restricted@example.test',
            'user' => [
                'id' => 133,
                'name' => 'Restricted Student',
                'email' => 'restricted@example.test',
                'role' => 'student',
            ],
        ];

        $this->withSession($adminSession)
            ->put(route('admin.applications.update', $applicant), [
                'status' => 'Approved',
            ])
            ->assertRedirect();

        $this->assertSame('pending', $applicant->fresh()->admission_status);

        $this->withSession($adminSession)
            ->post(route('exam.schedules.score', [$schedule, $applicant]), [
                'score' => 10,
                'total_items' => 10,
            ])
            ->assertForbidden();

        $this->withSession($studentSession)
            ->post(route('exam.schedules.score', [$schedule, $applicant]), [
                'score' => 10,
                'total_items' => 10,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('exam_scores', [
            'applicant_id' => $applicant->id,
            'exam_schedule_id' => $schedule->id,
        ]);
    }

    public function test_portal_admission_officer_role_has_officer_access(): void
    {
        $applicant = Applicant::create([
            'deoris_user_id' => 201,
            'portal_student_name' => 'Portal Applicant',
            'portal_student_email' => 'portal.applicant@example.test',
            'grade_level' => 'Grade 7',
            'status' => 'Pending',
            'admission_status' => 'pending',
        ]);

        $officerSession = [
            'sso_role' => 'admission_officer',
            'sso_name' => 'Admission Officer',
            'sso_email' => 'officer@example.test',
            'sso_id' => 6,
        ];

        $this->withSession($officerSession)
            ->get(route('registrar.applications'))
            ->assertOk()
            ->assertSee('Applicant Review Queue');

        $this->withSession($officerSession)
            ->put(route('registrar.application.update', $applicant), [
                'status' => 'Approved',
                'notes' => 'Approved through portal role.',
            ])
            ->assertRedirect();

        $applicant->refresh();
        $this->assertSame('Approved', $applicant->status);
        $this->assertSame('approved', $applicant->admission_status);
    }

    public function test_only_students_can_apply_and_students_only_manage_their_own_applications(): void
    {
        config(['deoris.portal.publish_enabled' => false]);
        Storage::fake('private');

        $ownerSession = [
            'sso_id' => 201,
            'sso_role' => 'student',
            'sso_name' => 'Owner Student',
            'sso_email' => 'owner@example.test',
            'user' => [
                'id' => 201,
                'name' => 'Owner Student',
                'email' => 'owner@example.test',
                'role' => 'student',
            ],
        ];

        $otherSession = [
            'sso_id' => 202,
            'sso_role' => 'student',
            'sso_name' => 'Other Student',
            'sso_email' => 'other@example.test',
            'user' => [
                'id' => 202,
                'name' => 'Other Student',
                'email' => 'other@example.test',
                'role' => 'student',
            ],
        ];

        $this->withSession([
            'sso_id' => 1,
            'sso_role' => 'admin',
            'sso_name' => 'Admin',
            'sso_email' => 'admin@example.test',
        ])
            ->get(route('student.apply'))
            ->assertForbidden();

        $this->withSession([
            'sso_id' => 2,
            'sso_role' => 'registrar',
            'sso_name' => 'Registrar',
            'sso_email' => 'registrar@example.test',
        ])
            ->get(route('student.apply'))
            ->assertForbidden();

        $this->withSession([
            'sso_id' => 1,
            'sso_role' => 'admin',
            'sso_name' => 'Admin',
            'sso_email' => 'admin@example.test',
        ])
            ->postJson('/api/student/apply', ['grade_level' => 'Grade 7'])
            ->assertForbidden();

        $this->withSession([
            'sso_id' => 2,
            'sso_role' => 'registrar',
            'sso_name' => 'Registrar',
            'sso_email' => 'registrar@example.test',
        ])
            ->postJson('/api/student/apply', ['grade_level' => 'Grade 7'])
            ->assertForbidden();

        $this->withSession($ownerSession)
            ->postJson('/api/student/apply', ['grade_level' => 'Grade 8'])
            ->assertUnprocessable();

        $this->withSession($ownerSession)
            ->post(route('student.apply.store'), [
                'phone' => '09170000002',
                'previous_school' => 'Owner Elementary School',
                'photo_2x2' => UploadedFile::fake()->create('owner-photo.jpg', 128, 'image/jpeg'),
                'psa_birth_cert' => UploadedFile::fake()->create('owner-psa.pdf', 128, 'application/pdf'),
            ])
            ->assertRedirect(route('student.applications'));

        $ownerApplication = Applicant::where('deoris_user_id', 201)->firstOrFail();

        $otherApplication = Applicant::create([
            'deoris_user_id' => 202,
            'portal_student_name' => 'Other Student',
            'portal_student_email' => 'other@example.test',
            'grade_level' => 'Grade 7',
            'status' => 'Pending',
            'admission_status' => 'pending',
        ]);

        $this->withSession($ownerSession)
            ->getJson('/api/student/applications')
            ->assertOk()
            ->assertJsonFragment(['id' => $ownerApplication->id])
            ->assertJsonMissing(['id' => $otherApplication->id]);

        $this->withSession($ownerSession)
            ->get(route('student.applications'))
            ->assertOk()
            ->assertSee('owner@example.test')
            ->assertDontSee('other@example.test');

        $this->withSession($ownerSession)
            ->putJson("/api/student/applications/{$otherApplication->id}", ['grade_level' => 'Grade 7'])
            ->assertForbidden();

        $this->withSession($ownerSession)
            ->deleteJson("/api/student/applications/{$otherApplication->id}")
            ->assertForbidden();

        $this->withSession($ownerSession)
            ->putJson("/api/student/applications/{$ownerApplication->id}", ['grade_level' => 'Grade 7'])
            ->assertForbidden();

        $this->withSession($ownerSession)
            ->deleteJson("/api/student/applications/{$ownerApplication->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('applicants', ['id' => $ownerApplication->id]);
        $this->assertDatabaseHas('applicants', ['id' => $otherApplication->id]);
    }
}
