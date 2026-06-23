<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Models\AccelerationBenefitReport;
use App\Modules\Query\Models\QueryLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AccelerationBenefitReportService
{
    /**
     * @return array<string, mixed>
     */
    public function report(int $days = 1, ?string $date = null, ?int $datasetId = null, ?int $chartId = null): array
    {
        $query = $this->queryForPeriod($days, $date)
            ->when($datasetId !== null, fn (Builder $query) => $query->where('dataset_id', $datasetId))
            ->when($chartId !== null, fn (Builder $query) => $query->where('chart_id', $chartId));

        return $this->calculateFromQuery($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateSnapshot(?string $date = null, int $days = 1): array
    {
        $reportDate = $date !== null ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $overall = $this->report($days, $date);
        $count = 1;

        AccelerationBenefitReport::query()->updateOrCreate(
            [
                'report_date' => $reportDate,
                'dataset_id' => null,
                'chart_id' => null,
                'dashboard_id' => null,
            ],
            $this->reportAttributes($overall),
        );

        $datasetIds = $this->queryForPeriod($days, $date)
            ->whereNotNull('dataset_id')
            ->select('dataset_id')
            ->distinct()
            ->pluck('dataset_id');

        foreach ($datasetIds as $datasetId) {
            $report = $this->report($days, $date, (int) $datasetId);
            AccelerationBenefitReport::query()->updateOrCreate(
                [
                    'report_date' => $reportDate,
                    'dataset_id' => (int) $datasetId,
                    'chart_id' => null,
                    'dashboard_id' => null,
                ],
                $this->reportAttributes($report),
            );
            $count++;
        }

        return [
            ...$overall,
            'report_date' => $reportDate,
            'snapshot_count' => $count,
        ];
    }

    private function queryForPeriod(int $days, ?string $date): Builder
    {
        if ($date !== null) {
            $day = Carbon::parse($date);

            return QueryLog::query()->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        }

        return QueryLog::query()->where('created_at', '>=', now()->subDays(max($days, 1)));
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateFromQuery(Builder $query): array
    {
        $queryCount = (clone $query)->count();
        $raw = (clone $query)->where('acceleration_hit', false)->where('cached', false);
        $detail = (clone $query)->where('acceleration_hit', true)->where('acceleration_mode', 'detail_table')->where('cached', false);
        $aggregate = (clone $query)->where('acceleration_hit', true)->where('acceleration_mode', 'aggregate_table')->where('cached', false);
        $cache = (clone $query)->where('cached', true);
        $rawCount = (clone $raw)->count();
        $detailCount = (clone $detail)->count();
        $aggregateCount = (clone $aggregate)->count();
        $cacheCount = (clone $cache)->count();
        $rawAvg = $this->avgDuration($raw);
        $acceleratedTotal = (int) (clone $detail)->sum('elapsed_ms') + (int) (clone $aggregate)->sum('elapsed_ms') + (int) (clone $cache)->sum('elapsed_ms');
        $acceleratedCount = $detailCount + $aggregateCount + $cacheCount;

        return [
            'query_count' => $queryCount,
            'raw_query_count' => $rawCount,
            'detail_hit_count' => $detailCount,
            'aggregate_hit_count' => $aggregateCount,
            'cache_hit_count' => $cacheCount,
            'fallback_count' => (clone $query)->where('fallback_used', true)->count(),
            'avg_raw_duration_ms' => $rawAvg,
            'avg_detail_duration_ms' => $this->avgDuration($detail),
            'avg_aggregate_duration_ms' => $this->avgDuration($aggregate),
            'avg_cache_duration_ms' => $this->avgDuration($cache),
            'estimated_saved_ms' => $rawAvg !== null && $acceleratedCount > 0
                ? ($rawAvg * $acceleratedCount) - $acceleratedTotal
                : null,
        ];
    }

    private function avgDuration(Builder $query): ?int
    {
        $avg = (clone $query)->avg('elapsed_ms');

        return $avg === null ? null : (int) round((float) $avg);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function reportAttributes(array $report): array
    {
        return collect($report)
            ->only([
                'query_count',
                'raw_query_count',
                'detail_hit_count',
                'aggregate_hit_count',
                'cache_hit_count',
                'fallback_count',
                'avg_raw_duration_ms',
                'avg_detail_duration_ms',
                'avg_aggregate_duration_ms',
                'avg_cache_duration_ms',
                'estimated_saved_ms',
            ])
            ->all();
    }
}
