<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FederatedSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_service_unavailable_when_token_not_configured(): void
    {
        config(['deoris.search_token' => '']);

        $this->withSession([
            'sso_id' => 1,
            'sso_role' => 'admin',
            'sso_name' => 'Admin Search',
            'sso_email' => 'admin.search@example.test',
        ])->getJson('/api/search?q=Ma')
            ->assertStatus(503)
            ->assertJsonPath('module', 'EntryEase');
    }

    public function test_search_requires_valid_bearer_token(): void
    {
        config(['deoris.search_token' => 'portal-shared-token']);

        $session = [
            'sso_id' => 1,
            'sso_role' => 'admin',
            'sso_name' => 'Admin Search',
            'sso_email' => 'admin.search@example.test',
        ];

        $this->withSession($session)->getJson('/api/search?q=Maria')->assertUnauthorized();

        $this->withSession($session)->getJson('/api/search?q=Maria', [
            'Authorization' => 'Bearer wrong',
        ])->assertUnauthorized();
    }

    public function test_search_validates_query_length(): void
    {
        config(['deoris.search_token' => 'portal-shared-token']);

        $this->withSession([
            'sso_id' => 1,
            'sso_role' => 'admin',
            'sso_name' => 'Admin Search',
            'sso_email' => 'admin.search@example.test',
        ])->getJson('/api/search?q=a', [
            'Authorization' => 'Bearer portal-shared-token',
        ])->assertUnprocessable();
    }

    public function test_search_returns_student_matches(): void
    {
        config(['deoris.search_token' => 'portal-shared-token']);

        \App\Models\Applicant::create([
            'deoris_user_id' => 9001,
            'portal_student_name' => 'Maria Santos',
            'portal_student_email' => 'maria@example.test',
            'grade_level' => 'Grade 7',
            'status' => 'Pending',
            'admission_status' => 'pending',
        ]);

        $response = $this->withSession([
            'sso_id' => 1,
            'sso_role' => 'admin',
            'sso_name' => 'Admin Search',
            'sso_email' => 'admin.search@example.test',
        ])->getJson('/api/search?q=Maria&limit=5', [
            'Authorization' => 'Bearer portal-shared-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Maria Santos')
            ->assertJsonPath('data.0.subtitle', 'maria@example.test')
            ->assertJsonPath('data.0.meta.module', 'EntryEase');
    }
}
