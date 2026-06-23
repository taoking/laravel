<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Chart\Models\Chart;
use App\Modules\Query\Models\QueryLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QueryLogAnalysisService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 7, ?int $datasetId = null): array
    {
        $query = $this->baseQuery($days, $datasetId);

        return [
            'days' => $days,
            'dataset_id' => $datasetId,
            'query_count' => (clone $query)->count(),
            'slow_query_count' => (clone $query)->where('elapsed_ms', '>=', (int) config('bi_acceleration.recommendation.slow_query_threshold_ms', 3000))->count(),
            'fallback_count' => (clone $query)->where('fallback_used', true)->count(),
            'aggregate_hit_count' => (clone $query)->where('acceleration_mode', 'aggregate_table')->where('acceleration_hit', true)->count(),
            'detail_hit_count' => (clone $query)->where('acceleration_mode', 'detail_table')->where('acceleration_hit', true)->count(),
            'raw_query_count' => (clone $query)->where('acceleration_hit', false)->where('cached', false)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function analyzeSlowQueries(int $days = 7, ?int $datasetId = null): array
    {
        $threshold = (int) config('bi_acceleration.recommendation.slow_query_threshold_ms', 3000);

        $logs = $this->baseQuery($days, $datasetId)
            ->with('chart')
            ->where('elapsed_ms', '>=', $threshold)
            ->where(fn (Builder $query) => $query->whereNull('acceleration_mode')->orWhere('acceleration_mode', '!=', 'aggregate_table'))
            ->latest('id')
            ->get();

        return $this->chartGroups($logs, 'slow_query');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function analyzeFrequentCharts(int $days = 7, ?int $datasetId = null): array
    {
        $logs = $this->baseQuery($days, $datasetId)
            ->with('chart')
            ->whereNotNull('chart_id')
            ->where(fn (Builder $query) => $query->whereNull('acceleration_mode')->orWhere('acceleration_mode', '!=', 'aggregate_table'))
            ->latest('id')
            ->get();

        $threshold = (int) config('bi_acceleration.recommendation.high_frequency_threshold', 30);

        return collect($this->chartGroups($logs, 'frequent_chart'))
            ->filter(fn (array $group): bool => $group['query_count'] >= $threshold)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function analyzeFallbackReasons(int $days = 7, ?int $datasetId = null): array
    {
        $min = (int) config('bi_acceleration.recommendation.min_query_count', 5);

        return $this->baseQuery($days, $datasetId)
            ->where('fallback_used', true)
            ->get()
            ->groupBy(fn (QueryLog $log): string => ((string) $log->dataset_id).'|'.((string) $log->fallback_reason))
            ->map(function (Collection $logs): array {
                /** @var QueryLog $first */
                $first = $logs->first();

                return $this->groupStats($logs, 'fallback_reason', $first->chart, $first->fallback_reason);
            })
            ->filter(fn (array $group): bool => $group['query_count'] >= $min)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function analyzeRawDatasets(int $days = 7, ?int $datasetId = null): array
    {
        $min = 20;

        return $this->baseQuery($days, $datasetId)
            ->where('acceleration_hit', false)
            ->where('cached', false)
            ->get()
            ->groupBy('dataset_id')
            ->map(function (Collection $logs): array {
                /** @var QueryLog $first */
                $first = $logs->first();

                return $this->groupStats($logs, 'raw_dataset', $first->chart);
            })
            ->filter(fn (array $group): bool => $group['query_count'] >= $min && ($group['avg_duration_ms'] ?? 0) >= 2000)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function analyzeDatasetQueryPatterns(int $datasetId, int $days = 7): array
    {
        $logs = $this->baseQuery($days, $datasetId)
            ->with('chart')
            ->whereNotNull('chart_id')
            ->latest('id')
            ->get();

        return $this->chartGroups($logs, 'dataset_pattern');
    }

    private function baseQuery(int $days, ?int $datasetId = null): Builder
    {
        return QueryLog::query()
            ->where('created_at', '>=', now()->subDays(max($days, 1)))
            ->when($datasetId !== null, fn (Builder $query) => $query->where('dataset_id', $datasetId));
    }

    /**
     * @param  Collection<int, QueryLog>  $logs
     * @return list<array<string, mixed>>
     */
    private function chartGroups(Collection $logs, string $rule): array
    {
        return $logs
            ->filter(fn (QueryLog $log): bool => $log->chart instanceof Chart)
            ->groupBy('chart_id')
            ->map(function (Collection $logs) use ($rule): array {
                /** @var QueryLog $first */
                $first = $logs->first();

                return $this->groupStats($logs, $rule, $first->chart);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, QueryLog>  $logs
     * @return array<string, mixed>
     */
    private function groupStats(Collection $logs, string $rule, ?Chart $chart = null, ?string $reason = null): array
    {
        /** @var QueryLog $first */
        $first = $logs->first();
        $durations = $logs->pluck('elapsed_ms')->map(fn (mixed $value): int => (int) $value);

        return [
            'rule' => $rule,
            'dataset_id' => (int) $first->dataset_id,
            'chart_id' => $chart?->id ?? $first->chart_id,
            'dashboard_id' => $first->dashboard_id,
            'chart' => $chart,
            'query_count' => $logs->count(),
            'avg_duration_ms' => (int) round($durations->avg() ?? 0),
            'max_duration_ms' => (int) ($durations->max() ?? 0),
            'total_duration_ms' => (int) $durations->sum(),
            'source_query_log_ids' => $logs->pluck('id')->take(50)->values()->all(),
            'reason_detail' => $reason,
        ];
    }
}
