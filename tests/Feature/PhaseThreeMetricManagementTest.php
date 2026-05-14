<?php

namespace Tests\Feature;

use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricCategory;
use App\Domains\Metrics\Models\Region;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeMetricManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_list_supports_filtering_sorting_and_pagination(): void
    {
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();
        $region = Region::query()->where('code', 'CN-SH')->firstOrFail();

        $this->actingAs($analyst)
            ->getJson("/api/v1/metrics?keyword=Revenue&category_code=finance&region_id={$region->id}&frequency_code=monthly&sort=name&direction=asc&per_page=10")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.code', 'revenue_amount')
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_metric_list_rejects_non_whitelisted_sort_field(): void
    {
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        $this->actingAs($analyst)
            ->getJson('/api/v1/metrics?sort=deleted_at')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['sort']]);
    }

    public function test_metric_crud_requires_manage_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();
        $category = MetricCategory::query()->where('code', 'business')->firstOrFail();

        $payload = [
            'metric_category_id' => $category->id,
            'name' => 'Conversion Rate',
            'code' => 'conversion_rate',
            'unit' => '%',
            'status' => 'active',
            'description' => 'Conversion rate by period.',
        ];

        $this->actingAs($analyst)
            ->postJson('/api/v1/metrics', $payload)
            ->assertForbidden();

        $metricId = $this->actingAs($admin)
            ->postJson('/api/v1/metrics', $payload)
            ->assertCreated()
            ->assertJsonPath('data.metric.code', 'conversion_rate')
            ->json('data.metric.id');

        $this->actingAs($admin)
            ->getJson("/api/v1/metrics/{$metricId}")
            ->assertOk()
            ->assertJsonPath('data.metric.category.code', 'business');

        $this->actingAs($admin)
            ->putJson("/api/v1/metrics/{$metricId}", array_replace($payload, [
                'name' => 'Conversion Rate Updated',
            ]))
            ->assertOk()
            ->assertJsonPath('data.metric.name', 'Conversion Rate Updated');

        $this->actingAs($admin)
            ->deleteJson("/api/v1/metrics/{$metricId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted(Metric::class, ['id' => $metricId]);
    }

    public function test_metric_dimensions_are_available_to_metric_viewers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        $this->actingAs($analyst)
            ->getJson('/api/v1/metric-categories')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'business');

        $this->actingAs($analyst)
            ->getJson('/api/v1/dimensions/regions')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'CN');

        $this->actingAs($analyst)
            ->getJson('/api/v1/dimensions/frequencies')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'monthly');
    }

    public function test_openapi_documents_phase_three_metric_endpoints(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('/api/v1/metrics')
            ->assertSee('/api/v1/metric-categories')
            ->assertSee('/api/v1/dimensions/regions')
            ->assertSee('/api/v1/dimensions/frequencies');
    }
}
