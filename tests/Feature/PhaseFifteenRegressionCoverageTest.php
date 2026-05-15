<?php

namespace Tests\Feature;

use App\Domains\Imports\Models\ExportTask;
use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PhaseFifteenRegressionCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_crud_api_endpoints_return_standard_unauthorized_json(): void
    {
        $requests = [
            ['GET', '/api/v1/users', []],
            ['POST', '/api/v1/metrics', []],
            ['POST', '/api/v1/imports', []],
            ['POST', '/api/v1/exports', []],
            ['GET', '/api/v1/audit-logs', []],
        ];

        foreach ($requests as [$method, $uri, $payload]) {
            $this->sendJson($method, $uri, $payload)
                ->assertUnauthorized()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Unauthenticated.')
                ->assertJsonStructure(['success', 'message', 'errors', 'trace_id']);
        }
    }

    public function test_admin_validation_errors_use_stable_json_contracts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $metric = Metric::query()->where('code', 'revenue_amount')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'Duplicate Admin',
                'email' => 'admin@example.com',
                'password' => 'password123',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['email'], 'trace_id']);

        $this->actingAs($admin)
            ->postJson('/api/v1/metrics', [
                'metric_category_id' => 999999,
                'name' => '<script>alert(1)</script>',
                'code' => $metric->code,
                'status' => 'archived',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => [
                    'metric_category_id',
                    'name',
                    'code',
                    'status',
                ],
                'trace_id',
            ]);
    }

    public function test_analyst_role_can_read_metrics_but_cannot_mutate_restricted_modules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();
        $category = MetricCategory::query()->where('code', 'business')->firstOrFail();

        $this->actingAs($analyst)
            ->getJson('/api/v1/metrics')
            ->assertOk()
            ->assertJsonPath('success', true);

        $forbiddenRequests = [
            ['POST', '/api/v1/users', ['name' => 'Blocked', 'email' => 'blocked@example.com', 'password' => 'password123']],
            ['POST', '/api/v1/roles', ['name' => 'Blocked Role', 'code' => 'blocked_role', 'data_scope' => 'self']],
            ['POST', '/api/v1/metrics', [
                'metric_category_id' => $category->id,
                'name' => 'Blocked Metric',
                'code' => 'blocked_metric',
            ]],
            ['POST', '/api/v1/imports', []],
            ['POST', '/api/v1/exports', ['type' => 'metrics']],
            ['GET', '/api/v1/audit-logs', []],
        ];

        foreach ($forbiddenRequests as [$method, $uri, $payload]) {
            $this->actingAs($analyst);

            $this->sendJson($method, $uri, $payload)
                ->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonStructure(['success', 'message', 'errors', 'trace_id']);
        }
    }

    public function test_export_validation_rejects_unknown_type_without_creating_task(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/exports', ['type' => 'users'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['type'], 'trace_id']);

        $this->assertSame(0, ExportTask::query()->count());
    }

    public function test_missing_api_resources_use_standard_not_found_contract(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/metrics/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'trace_id']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendJson(string $method, string $uri, array $payload = []): TestResponse
    {
        return match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            default => $this->json($method, $uri, $payload),
        };
    }
}
