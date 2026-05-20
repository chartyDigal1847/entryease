<?php

namespace Tests\Feature;

use App\DTOs\Deoris\ApplicationSubmittedData;
use App\Events\Deoris\ApplicationSubmitted;
use App\Services\Integration\EcosystemEventMapper;
use App\Services\Integration\PortalEcosystemPublisher;
use Deoris\Integration\Support\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeorisPortalContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecosystem_mapper_matches_portal_contract(): void
    {
        config(['deoris.module_name' => 'EntryEase']);

        $data = new ApplicationSubmittedData(
            applicantId: 1,
            studentId: 2,
            studentEmail: 'student@deoris.test',
            studentName: 'Test Student',
            gradeLevel: 'Grade 7',
            status: 'Pending',
        );

        $ecosystem = app(EcosystemEventMapper::class)->toEcosystemEvent(
            new ApplicationSubmitted($data, 'corr-abc'),
        );

        $this->assertSame('ApplicationSubmitted', $ecosystem->name);
        $this->assertSame('EntryEase', $ecosystem->sourceModule);
        $this->assertSame('student@deoris.test', $ecosystem->payload['student_email']);
        $this->assertSame('Test Student', $ecosystem->payload['student_name']);
        $this->assertSame('corr-abc', $ecosystem->correlationId);
    }

    public function test_signature_matches_deoris_portal_algorithm(): void
    {
        $secret = 'test-entryease-secret';
        $body = json_encode([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'ApplicationSubmitted',
            'source_module' => 'EntryEase',
            'payload' => ['student_email' => 'a@b.test'],
            'occurred_at' => now()->toAtomString(),
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'schema_version' => '1.0',
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $nonce = (string) \Illuminate\Support\Str::uuid();
        $signature = Signature::sign($body, $secret, $timestamp, $nonce);

        $this->assertTrue(Signature::verify($body, $secret, $timestamp, $nonce, $signature));
    }

    public function test_domain_events_do_not_implement_should_broadcast_directly(): void
    {
        $interfaces = class_implements(ApplicationSubmitted::class);

        $this->assertArrayNotHasKey(
            \Illuminate\Contracts\Broadcasting\ShouldBroadcast::class,
            $interfaces ?: [],
        );
    }

    public function test_http_publish_payload_is_accepted_by_portal_shape(): void
    {
        Http::fake(['*' => Http::response(['accepted' => true], 202)]);

        config([
            'deoris.portal.publish_enabled' => true,
            'deoris.portal.event_secret' => 'test-entryease-secret',
            'deoris.portal.url' => 'https://deoris.test',
        ]);

        $data = new ApplicationSubmittedData(1, 2, 'student@deoris.test', 'Test', 'Grade 7', 'Pending');

        app(PortalEcosystemPublisher::class)->publish(new ApplicationSubmitted($data), async: false);

        Http::assertSent(function ($request) {
            $json = $request->data();

            return isset($json['name'], $json['source_module'], $json['payload'], $json['id'])
                && $json['name'] === 'ApplicationSubmitted'
                && $json['source_module'] === 'EntryEase';
        });
    }
}
