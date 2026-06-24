<?php

namespace App\Modules\Chart\Services;

use App\Models\User;
use App\Modules\Cache\Services\ChartCacheService;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Semantic\Services\MetricUsageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChartService
{
    public function __construct(
        private readonly ChartConfigValidator $configValidator,
        private readonly ChartCacheService $chartCacheService,
        private readonly MetricUsageService $metricUsageService,
    ) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return Chart::query()
            ->with('dataset')
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): Chart
    {
        $dataset = Dataset::query()->with('fields')->findOrFail($payload['dataset_id']);
        $this->configValidator->validate($dataset, $payload['chart_type'], $payload['config_json']);

        return DB::transaction(function () use ($payload, $actor, $dataset): Chart {
            $chart = Chart::query()->create([
                'tenant_id' => $payload['tenant_id'] ?? null,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'dataset_id' => $dataset->id,
                'chart_type' => $payload['chart_type'],
                'config_json' => $payload['config_json'],
                'style_json' => $payload['style_json'] ?? null,
                'status' => $payload['status'] ?? 'active',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);
            $this->metricUsageService->syncChart($chart);

            return $chart->load('dataset');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Chart $chart, array $payload, ?User $actor): Chart
    {
        $datasetId = $payload['dataset_id'] ?? $chart->dataset_id;
        $dataset = Dataset::query()->with('fields')->findOrFail($datasetId);
        $chartType = $payload['chart_type'] ?? $chart->chart_type;
        $config = $payload['config_json'] ?? $chart->config_json;

        $this->configValidator->validate($dataset, $chartType, $config);

        $updatedChart = DB::transaction(function () use ($chart, $payload, $dataset, $chartType, $config, $actor): Chart {
            $chart->fill([
                ...$payload,
                'dataset_id' => $dataset->id,
                'chart_type' => $chartType,
                'config_json' => $config,
                'updated_by' => $actor?->id,
            ]);
            $chart->save();
            $this->metricUsageService->syncChart($chart);

            return $chart->refresh()->load('dataset');
        });

        $this->chartCacheService->forget($updatedChart);

        return $updatedChart;
    }

    public function delete(Chart $chart): void
    {
        $this->chartCacheService->forget($chart);
        $this->metricUsageService->forgetChart($chart);
        $chart->delete();
    }
}
