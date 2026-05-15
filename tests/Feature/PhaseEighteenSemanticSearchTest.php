<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseEighteenSemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_semantic_search_expands_business_terms_to_revenue_metric(): void
    {
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        $response = $this->actingAs($analyst)
            ->getJson('/api/v1/metrics/semantic-search?q=income%20sales&limit=3')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.results.0.metric.code', 'revenue_amount')
            ->assertJsonPath('data.results.0.engine', 'local-token-vector');

        $this->assertContains('revenue', $response->json('data.results.0.matched_terms'));
    }

    public function test_semantic_search_finds_active_users_from_related_terms(): void
    {
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        $this->actingAs($analyst)
            ->getJson('/api/v1/metrics/semantic-search?q=people%20activity')
            ->assertOk()
            ->assertJsonPath('data.results.0.metric.code', 'active_users');
    }

    public function test_semantic_search_validates_query_and_respects_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->getJson('/api/v1/metrics/semantic-search?q=income')
            ->assertUnauthorized();

        $this->actingAs($admin)
            ->getJson('/api/v1/metrics/semantic-search')
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['q'], 'trace_id']);
    }

    public function test_openapi_documents_semantic_search_endpoint(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('/api/v1/metrics/semantic-search')
            ->assertSee('语义搜索指标')
            ->assertSee('local-token-vector');
    }
}
