<?php

namespace Tests\Feature;

use App\DTOs\Deoris\DeorisEventEnvelope;
use App\Jobs\ProcessInboundDeorisEventJob;
use App\Jobs\PublishPortalEcosystemEventJob;
use App\Models\DeorisProcessedEvent;
use App\Services\Integration\EventSignatureService;
use App\Services\Integration\InboundEventDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeorisEventBusTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_submitted_publishes_to_portal_hub(): void
    {
        Http::fake();
        Queue::fake();

        config([
            'queue.default' => 'database',
            'deoris.portal.publish_enabled' => true,
            'deoris.portal.event_secret' => 'test-entryease-secret',
        ]);

        $applicant = \App\Models\Applicant::create([
            'deoris_user_id' => 1001,
            'portal_student_name' => 'Test Student',
            'portal_student_email' => 'bus.test@example.com',
            'grade_level' => 'Grade 7',
            'status' => 'Pending',
            'admission_status' => 'pending',
        ]);

        app(\App\Services\Admission\AdmissionEventService::class)
            ->applicationSubmitted($applicant);

        Queue::assertPushed(PublishPortalEcosystemEventJob::class);
    }

    public function test_inbound_tuition_paid_is_processed_idempotently(): void
    {
        config([
            'deoris.trusted_modules' => ['AssessPay'],
            'deoris.module_secrets.AssessPay' => 'test-secret',
            'deoris.signing_secret' => 'test-secret',
        ]);

        $envelope = new DeorisEventEnvelope(
            event: 'TuitionPaid',
            version: '1.0',
            source: 'AssessPay',
            timestamp: now()->toIso8601String(),
            correlationId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            data: [
                'student_email' => 'unknown@example.com',
                'student_external_id' => 'ext-1',
                'payment_reference' => 'PAY-001',
                'amount' => 1000,
                'paid_at' => now()->toIso8601String(),
            ],
        );

        $signature = app(EventSignatureService::class)->sign($envelope, 'test-secret');
        $payload = $envelope->toArray($signature);

        $dispatcher = app(InboundEventDispatcher::class);
        $dispatcher->dispatch(DeorisEventEnvelope::fromArray($payload));

        $this->assertTrue(DeorisProcessedEvent::wasProcessed($envelope->eventId));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\Integration\ReplayProtectionService::class)
            ->assertNotProcessed(DeorisEventEnvelope::fromArray($payload));
    }

    public function test_inbound_http_endpoint_accepts_signed_event(): void
    {
        Queue::fake();

        config([
            'deoris.trusted_modules' => ['AssessPay'],
            'deoris.module_secrets.AssessPay' => 'test-secret',
        ]);

        $envelope = new DeorisEventEnvelope(
            event: 'TuitionPaid',
            version: '1.0',
            source: 'AssessPay',
            timestamp: now()->toIso8601String(),
            correlationId: (string) Str::uuid(),
            eventId: (string) Str::uuid(),
            data: [
                'student_email' => 'http@example.com',
                'student_external_id' => 'ext-http',
                'payment_reference' => 'PAY-HTTP',
                'amount' => 500,
                'paid_at' => now()->toIso8601String(),
            ],
        );

        $signature = app(EventSignatureService::class)->sign($envelope, 'test-secret');
        $payload = $envelope->toArray($signature);

        $response = $this->withSession([
            'sso_id' => 5001,
            'sso_role' => 'registrar',
            'sso_name' => 'Registrar EventBus',
            'sso_email' => 'registrar.eventbus@example.test',
        ])->postJson('/entryease/api/events/inbound', $payload);

        $response->assertAccepted();
        Queue::assertPushed(ProcessInboundDeorisEventJob::class);
    }
}
