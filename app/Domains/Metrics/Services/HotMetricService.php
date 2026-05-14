<?php

namespace App\Domains\Metrics\Services;

use App\Domains\Metrics\Models\Metric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HotMetricService
{
    private const KEY = 'metrics:hot';

    public function record(Metric $metric): void
    {
        try {
            Redis::zincrby(self::KEY, 1, $metric->code);
            Redis::expire(self::KEY, $this->hotTtlSeconds());

            return;
        } catch (Throwable) {
            $scores = Cache::get(self::KEY, []);
            $scores[$metric->code] = ($scores[$metric->code] ?? 0) + 1;
            arsort($scores);
            Cache::put(self::KEY, $scores, $this->hotTtlSeconds());
        }
    }

    public function top(int $limit = 10): array
    {
        try {
            return Redis::zrevrange(self::KEY, 0, $limit - 1, ['withscores' => true]);
        } catch (Throwable) {
            return array_slice(Cache::get(self::KEY, []), 0, $limit, true);
        }
    }

    public function hotTtlSeconds(): int
    {
        return 3600 + random_int(0, 300);
    }
}
