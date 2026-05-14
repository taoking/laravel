<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricCategory;
use App\Domains\Metrics\Services\HotMetricService;
use App\Domains\Operations\Models\OperationLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFiveSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_detail_is_cached_and_mutations_clear_cache_with_audit_log(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $metric = Metric::query()->where('code', 'revenue_amount')->firstOrFail();
        $category = MetricCategory::query()->where('code', 'finance')->firstOrFail();

        $this->actingAs($admin)
            ->getJson("/api/v1/metrics/{$metric->id}")
            ->assertOk();

        $this->assertTrue(Cache::has("metrics:detail:{$metric->id}"));
        $this->assertArrayHasKey('revenue_amount', app(HotMetricService::class)->top());

        $this->actingAs($admin)
            ->putJson("/api/v1/metrics/{$metric->id}", [
                'metric_category_id' => $category->id,
                'name' => 'Revenue Amount Secure',
                'code' => 'revenue_amount',
                'unit' => 'CNY',
                'status' => 'active',
            ])
            ->assertOk();

        $this->assertFalse(Cache::has("metrics:detail:{$metric->id}"));
        $this->assertDatabaseHas(AuditLog::class, [
            'user_id' => $admin->id,
            'action' => 'metric.updated',
            'resource_type' => Metric::class,
            'resource_id' => $metric->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/audit-logs?action=metric.updated')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'metric.updated');
    }

    public function test_metrics_query_rate_limit_returns_429(): void
    {
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($analyst)
                ->getJson('/api/v1/metrics')
                ->assertOk();
        }

        $this->actingAs($analyst)
            ->getJson('/api/v1/metrics')
            ->assertTooManyRequests();
    }

    public function test_signed_api_rejects_invalid_signature_and_replay(): void
    {
        Config::set('services.api_signature.secret', 'test-secret');

        $this->postJson('/api/v1/security/signed-echo', ['ping' => 'pong'])
            ->assertUnauthorized();

        $headers = $this->signatureHeaders(['ping' => 'pong'], 'nonce-1');

        $this->withHeaders($headers)
            ->postJson('/api/v1/security/signed-echo', ['ping' => 'pong'])
            ->assertOk()
            ->assertJsonPath('data.payload.ping', 'pong');

        $this->withHeaders($headers)
            ->postJson('/api/v1/security/signed-echo', ['ping' => 'pong'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_invalid_import_file_type_is_rejected(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post('/api/v1/imports', [
                'file' => UploadedFile::fake()->create('payload.php', 1, 'application/x-php'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_operation_log_dashboard_cache_and_xss_validation_are_active(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $category = MetricCategory::query()->where('code', 'finance')->firstOrFail();

        $this->getJson('/api/v1/health')->assertOk();
        $this->assertDatabaseHas(OperationLog::class, [
            'method' => 'GET',
            'path' => '/api/v1/health',
            'status_code' => 200,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
        $this->assertTrue(Cache::has('dashboard:summary'));

        $this->actingAs($admin)
            ->postJson('/api/v1/metrics', [
                'metric_category_id' => $category->id,
                'name' => '<script>alert(1)</script>',
                'code' => 'xss_metric',
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_openapi_documents_phase_five_security_endpoint(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('/api/v1/security/signed-echo');
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('/api/v1/audit-logs');
    }

    private function signatureHeaders(array $body, string $nonce): array
    {
        $timestamp = (string) now()->timestamp;
        $json = json_encode($body);
        $payload = implode('|', [
            'POST',
            '/api/v1/security/signed-echo',
            $timestamp,
            $nonce,
            $json,
        ]);

        return [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => hash_hmac('sha256', $payload, 'test-secret'),
        ];
    }
}
