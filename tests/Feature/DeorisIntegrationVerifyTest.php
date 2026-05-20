<?php

namespace Tests\Feature;

use App\Events\Deoris\ApplicationSubmitted;
use App\Jobs\PublishPortalEcosystemEventJob;
use App\Models\Applicant;
use App\Models\Student;
use App\Services\Admission\AdmissionEventService;
use App\Support\DeorisBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeorisIntegrationVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_admission_flow_dispatches_portal_job_without_broadcast_crash(): void
    {
        Http::fake();
        Queue::fake();

        config([
            'queue.default' => 'database',
            'deoris.portal.publish_enabled' => true,
            'deoris.portal.event_secret' => 'test-entryease-secret',
            'broadcasting.default' => 'reverb',
            'deoris.broadcast.enabled' => false,
        ]);

        $this->assertFalse(DeorisBroadcast::isEnabled());

        $student = Student::factory()->create([
            'email' => 'verify@deoris.test',
            'full_name' => 'Verify Student',
            'phone' => '09170000000',
            'password' => 'secret',
        ]);

        $applicant = Applicant::create([
            'student_id' => $student->id,
            'grade_level' => 'Grade 7',
            'status' => 'Pending',
            'admission_status' => 'pending',
        ]);

        app(AdmissionEventService::class)->applicationSubmitted($applicant);

        Queue::assertPushed(PublishPortalEcosystemEventJob::class);

        $this->assertTrue(true);
    }
}
