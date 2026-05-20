<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FederatedSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_service_unavailable_when_token_not_configured(): void
    {
        config(['deoris.search_token' => '']);

        $this->getJson('/api/search?q=Ma')
            ->assertStatus(503)
            ->assertJsonPath('module', 'EntryEase');
    }

    public function test_search_requires_valid_bearer_token(): void
    {
        config(['deoris.search_token' => 'portal-shared-token']);

        $this->getJson('/api/search?q=Maria')->assertUnauthorized();

        $this->getJson('/api/search?q=Maria', [
            'Authorization' => 'Bearer wrong',
        ])->assertUnauthorized();
    }

    public function test_search_validates_query_length(): void
    {
        config(['deoris.search_token' => 'portal-shared-token']);

        $this->getJson('/api/search?q=a', [
            'Authorization' => 'Bearer portal-shared-token',
        ])->assertUnprocessable();
    }

    public function test_search_returns_student_matches(): void
    {
        config(['deoris.search_token' => 'portal-shared-token']);

        Student::factory()->create([
            'full_name' => 'Maria Santos',
            'email' => 'maria@example.test',
        ]);

        $response = $this->getJson('/api/search?q=Maria&limit=5', [
            'Authorization' => 'Bearer portal-shared-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Maria Santos')
            ->assertJsonPath('data.0.subtitle', 'maria@example.test')
            ->assertJsonPath('data.0.meta.module', 'EntryEase');
    }
}
