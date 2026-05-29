<?php

namespace Tests\Feature;

use App\DTOs\Deoris\ApplicationSubmittedData;
use App\Events\Deoris\ApplicationSubmitted;
use App\Jobs\PublishPortalEcosystemEventJob;
use App\Services\Integration\PortalEcosystemPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalEcosystemPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_submitted_dispatches_portal_publish_job(): void
    {
        Queue::fake();
        Http::fake();

        config([
            'queue.default' => 'database',
            'deoris.portal.publish_enabled' => true,
            'deoris.portal.event_secret' => 'test-entryease-secret',
            'deoris.portal.url' => 'https://deoris.test',
        ]);

        $data = new ApplicationSubmittedData(
            applicantId: 1,
            studentId: 1,
            studentEmail: 'student@deoris.test',
            studentName: 'Test Student',
            gradeLevel: 'Grade 7',
            status: 'Pending',
        );

        app(PortalEcosystemPublisher::class)->publish(
            new ApplicationSubmitted($data, 'corr-123'),
        );

        Queue::assertPushed(PublishPortalEcosystemEventJob::class, function ($job) {
            return $job->eventPayload['name'] === 'ApplicationSubmitted'
                && $job->eventPayload['source_module'] === 'EntryEase'
                && $job->eventPayload['payload']['student_email'] === 'student@deoris.test';
        });
    }

    public function test_portal_http_publish_uses_deoris_signature_headers(): void
    {
        Http::fake([
            'https://deoris.test/*' => Http::response(['accepted' => true], 202),
        ]);

        config([
            'deoris.portal.publish_enabled' => true,
            'deoris.portal.event_secret' => 'test-entryease-secret',
            'deoris.portal.url' => 'https://deoris.test',
        ]);

        $data = new ApplicationSubmittedData(
            applicantId: 1,
            studentId: 1,
            studentEmail: 'student@deoris.test',
            studentName: 'Test Student',
            gradeLevel: 'Grade 7',
            status: 'Pending',
        );

        app(PortalEcosystemPublisher::class)->publish(
            new ApplicationSubmitted($data),
            async: false,
        );

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://deoris.test/')
                && $request->hasHeader('X-DEORIS-Module')
                && $request->hasHeader('X-DEORIS-Signature')
                && $request['name'] === 'ApplicationSubmitted'
                && $request['payload']['student_email'] === 'student@deoris.test';
        });
    }
}
