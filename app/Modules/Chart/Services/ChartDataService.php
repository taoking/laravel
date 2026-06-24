<?php

namespace App\Modules\Chart\Services;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Services\QueryOrchestrator;
use Illuminate\Validation\ValidationException;

class ChartDataService
{
    public function __construct(
        private readonly ChartConfigValidator $configValidator,
        private readonly ChartQueryBuilder $queryBuilder,
        private readonly QueryOrchestrator $queryOrchestrator,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function data(Chart $chart, array $overrides, ?User $actor, ?int $dashboardId = null): array
    {
        if ($chart->status !== 'active') {
            throw ValidationException::withMessages([
                'chart' => ['The selected chart is disabled.'],
            ]);
        }

        $chart->loadMissing('dataset.fields');
        $this->configValidator->validate($chart->dataset, $chart->chart_type, $chart->config_json);
        $overrides = $this->withDefaultCache($chart->config_json, $overrides);

        return $this->queryOrchestrator->execute(
            $this->queryBuilder->build($chart->dataset_id, $chart->config_json, $overrides),
            $actor,
            array_filter([
                'request_source' => $dashboardId !== null ? 'dashboard' : 'chart',
                'chart_id' => $chart->id,
                'dashboard_id' => $dashboardId,
            ], fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function preview(array $payload, ?User $actor): array
    {
        $dataset = Dataset::query()
            ->with('fields')
            ->findOrFail($payload['dataset_id']);

        $this->configValidator->validate($dataset, $payload['chart_type'], $payload['config_json']);

        return $this->queryOrchestrator->execute(
            $this->queryBuilder->build($dataset->id, $payload['config_json'], $payload['overrides'] ?? []),
            $actor,
            ['request_source' => 'chart_preview'],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function withDefaultCache(array $config, array $overrides): array
    {
        if (array_key_exists('use_cache', $config) || array_key_exists('use_cache', $overrides)) {
            return $overrides;
        }

        return [
            ...$overrides,
            'use_cache' => true,
        ];
    }
}
