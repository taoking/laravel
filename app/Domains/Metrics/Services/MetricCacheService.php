<?php

namespace App\Domains\Metrics\Services;

use App\Domains\Metrics\Models\Metric;
use App\Http\Resources\MetricResource;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class MetricCacheService
{
    private const EMPTY_PAYLOAD = ['__metric_cache_empty' => true];

    public function detail(Metric $metric): array
    {
        return $this->detailById((int) $metric->id) ?? MetricResource::make(
            $metric->load(['category', 'latestValue.region', 'latestValue.frequency'])
        )->resolve();
    }

    public function detailById(int $metricId): ?array
    {
        $cached = $this->cachedPayload($metricId);

        if ($cached !== false) {
            return $cached;
        }

        $lock = Cache::lock($this->lockKey($metricId), 10);

        try {
            return $lock->block(1, fn () => $this->rememberDetail($metricId));
        } catch (LockTimeoutException) {
            return $this->loadDetail($metricId);
        }
    }

    public function forget(int $metricId): void
    {
        Cache::forget($this->detailKey($metricId));
    }

    public function acquireRebuildLock(int $metricId, int $seconds = 10): ?string
    {
        $lock = Cache::lock($this->lockKey($metricId), $seconds);

        return $lock->get() ? $lock->owner() : null;
    }

    public function releaseRebuildLock(int $metricId, string $owner): bool
    {
        return Cache::restoreLock($this->lockKey($metricId), $owner)->release();
    }

    public function detailTtlSeconds(): int
    {
        return 600 + random_int(0, 120);
    }

    public function emptyTtlSeconds(): int
    {
        return 60;
    }

    public function detailKey(int $metricId): string
    {
        return "metrics:detail:{$metricId}";
    }

    public function lockKey(int $metricId): string
    {
        return "metrics:detail:{$metricId}:rebuild-lock";
    }

    private function rememberDetail(int $metricId): ?array
    {
        $cached = $this->cachedPayload($metricId);

        if ($cached !== false) {
            return $cached;
        }

        $payload = $this->loadDetail($metricId);

        if ($payload === null) {
            Cache::put($this->detailKey($metricId), self::EMPTY_PAYLOAD, $this->emptyTtlSeconds());

            return null;
        }

        Cache::put($this->detailKey($metricId), $payload, $this->detailTtlSeconds());

        return $payload;
    }

    private function loadDetail(int $metricId): ?array
    {
        $metric = Metric::query()
            ->with(['category', 'latestValue.region', 'latestValue.frequency'])
            ->find($metricId);

        if (! $metric) {
            return null;
        }

        return MetricResource::make($metric)->resolve();
    }

    /**
     * @return array<string, mixed>|false|null
     */
    private function cachedPayload(int $metricId): array|false|null
    {
        $payload = Cache::get($this->detailKey($metricId));

        if ($payload === self::EMPTY_PAYLOAD) {
            return null;
        }

        return is_array($payload) ? $payload : false;
    }
}
