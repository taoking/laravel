<?php

namespace App\Modules\Cache\Services;

use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Support\Facades\Cache;

class DatasetCacheService
{
    public function __construct(
        private readonly CacheKeyBuilder $keyBuilder,
        private readonly ChartCacheService $chartCacheService,
    ) {}

    public function forget(Dataset|int $dataset): void
    {
        $datasetId = $dataset instanceof Dataset ? (int) $dataset->id : $dataset;

        Cache::forget($this->keyBuilder->datasetSchema($datasetId));

        Chart::query()
            ->where('dataset_id', $datasetId)
            ->pluck('id')
            ->each(fn (int $chartId) => $this->chartCacheService->forget($chartId));
    }
}
