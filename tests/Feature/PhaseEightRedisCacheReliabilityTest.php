<?php

namespace Tests\Feature;

use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Services\HotMetricService;
use App\Domains\Metrics\Services\MetricCacheService;
use App\Domains\Metrics\Services\RedisRateLimiterService;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PhaseEightRedisCacheReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_detail_cache_stores_empty_payload_to_prevent_penetration(): void
    {
        Cache::flush();

        $cache = app(MetricCacheService::class);
        $missingMetricId = 999999;

        $this->assertNull($cache->detailById($missingMetricId));
        $this->assertTrue(Cache::has($cache->detailKey($missingMetricId)));
        $this->assertNull($cache->detailById($missingMetricId));
        $this->assertSame(60, $cache->emptyTtlSeconds());
    }

    public function test_metric_detail_cache_uses_token_lock_to_avoid_wrong_release(): void
    {
        Cache::flush();

        $cache = app(MetricCacheService::class);
        $owner = $cache->acquireRebuildLock(12345);

        $this->assertIsString($owner);
        $this->assertFalse($cache->releaseRebuildLock(12345, 'wrong-owner-token'));
        $this->assertTrue($cache->releaseRebuildLock(12345, $owner));
        $nextOwner = $cache->acquireRebuildLock(12345);
        $this->assertIsString($nextOwner);
        $this->assertTrue($cache->releaseRebuildLock(12345, $nextOwner));
    }

    public function test_metric_detail_cache_uses_random_ttl_and_clears_on_mutation(): void
    {
        Cache::flush();
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $metric = Metric::query()->where('code', 'revenue_amount')->firstOrFail();
        $cache = app(MetricCacheService::class);

        $ttl = $cache->detailTtlSeconds();
        $this->assertGreaterThanOrEqual(600, $ttl);
        $this->assertLessThanOrEqual(720, $ttl);

        $this->actingAs($admin)
            ->getJson("/api/v1/metrics/{$metric->id}")
            ->assertOk();

        $this->assertTrue(Cache::has($cache->detailKey((int) $metric->id)));

        $this->actingAs($admin)
            ->putJson("/api/v1/metrics/{$metric->id}", [
                'metric_category_id' => $metric->metric_category_id,
                'name' => 'Revenue Amount Redis Cache',
                'code' => 'revenue_amount',
                'unit' => 'CNY',
                'status' => 'active',
            ])
            ->assertOk();

        $this->assertFalse(Cache::has($cache->detailKey((int) $metric->id)));
    }

    public function test_hot_metric_fallback_uses_random_ttl_range(): void
    {
        $hotMetrics = app(HotMetricService::class);
        $ttl = $hotMetrics->hotTtlSeconds();

        $this->assertGreaterThanOrEqual(3600, $ttl);
        $this->assertLessThanOrEqual(3900, $ttl);
    }

    public function test_lua_rate_limiter_falls_back_without_redis_and_command_runs(): void
    {
        Cache::flush();

        $limiter = app(RedisRateLimiterService::class);
        $this->assertStringContainsString('INCR', $limiter->luaScript());
        $this->assertStringContainsString('EXPIRE', $limiter->luaScript());

        $this->assertTrue($limiter->allow('feature-test', 2, 60));
        $this->assertTrue($limiter->allow('feature-test', 2, 60));
        $this->assertFalse($limiter->allow('feature-test', 2, 60));

        $this->artisan('redis:cache-lab', [
            'action' => 'lua-rate-limit',
            '--key' => 'feature-command',
            '--limit' => 1,
            '--decay' => 60,
        ])->assertExitCode(0);
    }
}
