<?php

namespace App\Modules\Cache\Services;

use App\Modules\Chart\Models\Chart;
use App\Modules\Query\Services\QueryCacheService;
use Illuminate\Support\Facades\Cache;

class ChartCacheService
{
    public function __construct(
        private readonly CacheKeyBuilder $keyBuilder,
        private readonly QueryCacheService $queryCacheService,
    ) {}

    public function forget(Chart|int $chart): void
    {
        $chartId = $chart instanceof Chart ? (int) $chart->id : $chart;

        Cache::forget($this->keyBuilder->chartConfig($chartId));
        $this->queryCacheService->forgetChart($chartId);
    }
}
