<?php

namespace Tests\Feature;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Metrics\Models\MetricCategory;
use App\Events\AuditEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PhaseThirteenSecurityAttackLabTest extends TestCase
{
    use RefreshDatabase;

    public function test_ssrf_url_check_blocks_local_and_private_targets(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/security/url-check', [
                'url' => 'http://127.0.0.1/admin',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.url.0', 'private_or_reserved_ip_blocked');

        $this->actingAs($admin)
            ->postJson('/api/v1/security/url-check', [
                'url' => 'http://localhost/admin',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.url.0', 'local_hostname_blocked');
    }

    public function test_ssrf_url_check_allows_public_http_targets(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/security/url-check', [
                'url' => 'https://93.184.216.34/report.csv',
            ])
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.resolved_ips.0', '93.184.216.34');
    }

    public function test_sql_injection_like_keyword_is_treated_as_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        $this->actingAs($analyst)
            ->getJson('/api/v1/metrics?keyword=%27%20OR%201%3D1%20--')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_xss_payload_is_rejected_in_metric_description(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $category = MetricCategory::query()->where('code', 'finance')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/v1/metrics', [
                'metric_category_id' => $category->id,
                'name' => 'Security XSS Metric',
                'code' => 'security_xss_metric',
                'description' => '<script>alert(document.cookie)</script>',
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['description']]);
    }

    public function test_audit_metadata_masks_sensitive_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $request = Request::create('/api/v1/security/demo', 'POST');
        $request->setUserResolver(fn () => $admin);
        $request->attributes->set('trace_id', 'security-trace-id');

        AuditEvent::dispatch('security.sensitive-demo', null, null, [
            'password' => 'plain-text-password',
            'profile' => [
                'api_token' => 'secret-token',
                'safe_field' => 'visible',
            ],
        ], $request);

        $auditLog = AuditLog::query()->where('action', 'security.sensitive-demo')->firstOrFail();

        $this->assertSame('[FILTERED]', $auditLog->metadata['password']);
        $this->assertSame('[FILTERED]', $auditLog->metadata['profile']['api_token']);
        $this->assertSame('visible', $auditLog->metadata['profile']['safe_field']);
    }
}
