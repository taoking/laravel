<?php

namespace App\Modules\Metadata\Services;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\Metadata\Models\MetadataAsset;
use App\Modules\Metadata\Models\MetadataUsageStat;
use App\Modules\Query\Models\QueryLog;
use App\Modules\Semantic\Models\Metric;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MetadataUsageStatService
{
    public function __construct(private readonly MetadataAuthorizer $authorizer) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $pageSize, ?User $user): LengthAwarePaginator
    {
        $query = MetadataUsageStat::query()
            ->when($filters['asset_type'] ?? null, fn ($query, $type) => $query->where('asset_type', (string) $type))
            ->when($filters['asset_id'] ?? null, fn ($query, $id) => $query->where('asset_id', (int) $id))
            ->when($filters['usage_date'] ?? null, fn ($query, $date) => $query->whereDate('usage_date', (string) $date))
            ->when((bool) ($filters['high_slow'] ?? false), fn ($query) => $query->where('query_count', '>=', 10)->where('avg_duration_ms', '>=', 3000))
            ->when((bool) ($filters['low_frequency'] ?? false), fn ($query) => $query->where('query_count', '<=', 0));

        if (! $this->authorizer->canManage($user)) {
            $visibleKeys = $this->visibleAssetKeys($user);
            $query->where(function ($query) use ($visibleKeys): void {
                foreach ($visibleKeys as $key) {
                    [$type, $id] = explode(':', $key, 2);
                    $query->orWhere(fn ($query) => $query->where('asset_type', $type)->where('asset_id', (int) $id));
                }

                if ($visibleKeys === []) {
                    $query->whereRaw('1 = 0');
                }
            });
        }

        return $query->orderByDesc('usage_date')->orderByDesc('query_count')->paginate($pageSize);
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(?string $date = null, int $days = 1, bool $dryRun = false): array
    {
        $endDate = CarbonImmutable::parse($date ?: now())->endOfDay();
        $startDate = $endDate->subDays(max($days, 1) - 1)->startOfDay();
        $logs = QueryLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();
        $entries = [];
        $metricCache = [];
        $chartCache = [];
        $dashboardWidgetCache = [];

        foreach ($logs as $log) {
            $usageDate = $log->created_at->toDateString();

            if ($log->dataset_id !== null) {
                $this->addEntry($entries, $usageDate, 'dataset', (int) $log->dataset_id, $log);
            }

            if ($log->chart_id !== null) {
                $this->addEntry($entries, $usageDate, 'chart', (int) $log->chart_id, $log);

                foreach ($this->dashboardIdsForChart((int) $log->chart_id, $dashboardWidgetCache) as $dashboardId) {
                    $this->addEntry($entries, $usageDate, 'dashboard', $dashboardId, $log);
                }
            }

            if ($log->dashboard_id !== null) {
                $this->addEntry($entries, $usageDate, 'dashboard', (int) $log->dashboard_id, $log);
            }

            if ($log->acceleration_profile_id !== null) {
                $this->addEntry($entries, $usageDate, 'acceleration_profile', (int) $log->acceleration_profile_id, $log);
            }

            if ($log->aggregate_definition_id !== null) {
                $this->addEntry($entries, $usageDate, 'aggregate_definition', (int) $log->aggregate_definition_id, $log);
            }

            foreach ($this->metricIdsForLog($log, $metricCache, $chartCache) as $metricId) {
                $this->addEntry($entries, $usageDate, 'metric', $metricId, $log);
            }
        }

        $written = 0;

        foreach ($entries as $usageDate => $assets) {
            foreach ($assets as $key => $entry) {
                [$assetType, $assetId] = explode(':', $key, 2);
                $payload = [
                    'asset_type' => $assetType,
                    'asset_id' => (int) $assetId,
                    'usage_date' => $usageDate,
                    'query_count' => $entry['query_count'],
                    'view_count' => $entry['view_count'],
                    'edit_count' => 0,
                    'last_used_at' => $entry['last_used_at'],
                    'avg_duration_ms' => $entry['query_count'] > 0 ? (int) round($entry['duration_sum'] / $entry['query_count']) : null,
                    'slow_query_count' => $entry['slow_query_count'],
                ];

                if (! $dryRun) {
                    MetadataUsageStat::query()->updateOrCreate([
                        'asset_type' => $assetType,
                        'asset_id' => (int) $assetId,
                        'usage_date' => $usageDate,
                    ], $payload);
                }

                $written++;
            }
        }

        return [
            'dry_run' => $dryRun,
            'date' => $endDate->toDateString(),
            'days' => max($days, 1),
            'query_log_count' => $logs->count(),
            'stat_count' => $written,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(?User $user, int $days = 30): array
    {
        $since = now()->subDays($days)->toDateString();
        $assets = MetadataAsset::query();
        $this->authorizer->applyAssetVisibility($assets, $user);
        $assets = $assets->get();

        $usedKeys = MetadataUsageStat::query()
            ->where('usage_date', '>=', $since)
            ->where('query_count', '>', 0)
            ->get(['asset_type', 'asset_id'])
            ->map(fn (MetadataUsageStat $stat): string => $stat->asset_type.':'.$stat->asset_id)
            ->unique()
            ->all();

        $lowFrequency = $assets
            ->reject(fn (MetadataAsset $asset): bool => in_array($asset->asset_type.':'.$asset->asset_id, $usedKeys, true))
            ->take(20)
            ->map(fn (MetadataAsset $asset): array => $this->assetPayload($asset))
            ->values()
            ->all();

        $highSlow = MetadataUsageStat::query()
            ->where('usage_date', '>=', $since)
            ->where('query_count', '>=', 10)
            ->where('avg_duration_ms', '>=', 3000)
            ->orderByDesc('avg_duration_ms')
            ->limit(20)
            ->get()
            ->filter(fn (MetadataUsageStat $stat): bool => $this->canViewStat($stat, $user))
            ->map(fn (MetadataUsageStat $stat): array => [
                ...$stat->toArray(),
                'asset' => $this->assetPayload($this->assetForStat($stat)),
            ])
            ->values()
            ->all();

        return [
            'days' => $days,
            'low_frequency_assets' => $lowFrequency,
            'high_slow_assets' => $highSlow,
        ];
    }

    private function addEntry(array &$entries, string $usageDate, string $assetType, int $assetId, QueryLog $log): void
    {
        $key = $assetType.':'.$assetId;
        $entries[$usageDate][$key] ??= [
            'query_count' => 0,
            'view_count' => 0,
            'duration_sum' => 0,
            'slow_query_count' => 0,
            'last_used_at' => null,
        ];

        $entries[$usageDate][$key]['query_count']++;
        $entries[$usageDate][$key]['view_count']++;
        $entries[$usageDate][$key]['duration_sum'] += (int) $log->elapsed_ms;
        $entries[$usageDate][$key]['slow_query_count'] += ($log->is_slow || (int) $log->elapsed_ms >= 3000) ? 1 : 0;
        if ($entries[$usageDate][$key]['last_used_at'] === null || $log->created_at->greaterThan($entries[$usageDate][$key]['last_used_at'])) {
            $entries[$usageDate][$key]['last_used_at'] = $log->created_at;
        }
    }

    /**
     * @param  array<string, int>  $metricCache
     * @param  array<int, list<string>>  $chartCache
     * @return list<int>
     */
    private function metricIdsForLog(QueryLog $log, array &$metricCache, array &$chartCache): array
    {
        $codes = collect($log->semantic_metrics_json ?? [])
            ->map(fn (mixed $metric): ?string => is_array($metric) ? ($metric['metric_code'] ?? $metric['code'] ?? null) : (is_string($metric) ? $metric : null))
            ->filter()
            ->values();

        if ($codes->isEmpty() && $log->chart_id !== null) {
            $codes = collect($this->metricCodesForChart((int) $log->chart_id, $chartCache));
        }

        return $codes
            ->unique()
            ->map(function (string $code) use ($log, &$metricCache): ?int {
                if ($log->dataset_id === null) {
                    return null;
                }

                $key = $log->dataset_id.':'.$code;
                $metricCache[$key] ??= (int) (Metric::query()
                    ->where('dataset_id', $log->dataset_id)
                    ->where('code', $code)
                    ->value('id') ?? 0);

                return $metricCache[$key] > 0 ? $metricCache[$key] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, list<string>>  $chartCache
     * @return list<string>
     */
    private function metricCodesForChart(int $chartId, array &$chartCache): array
    {
        if (isset($chartCache[$chartId])) {
            return $chartCache[$chartId];
        }

        $config = Chart::query()->whereKey($chartId)->value('config_json');
        $config = is_string($config) ? json_decode($config, true) : $config;
        $chartCache[$chartId] = collect($config['semantic_metrics'] ?? [])
            ->map(fn (mixed $metric): ?string => is_array($metric) ? ($metric['metric_code'] ?? $metric['code'] ?? null) : (is_string($metric) ? $metric : null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $chartCache[$chartId];
    }

    /**
     * @param  array<int, list<int>>  $dashboardWidgetCache
     * @return list<int>
     */
    private function dashboardIdsForChart(int $chartId, array &$dashboardWidgetCache): array
    {
        $dashboardWidgetCache[$chartId] ??= DashboardWidget::query()
            ->where('chart_id', $chartId)
            ->pluck('dashboard_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $dashboardWidgetCache[$chartId];
    }

    /**
     * @return list<string>
     */
    private function visibleAssetKeys(?User $user): array
    {
        $query = MetadataAsset::query();
        $this->authorizer->applyAssetVisibility($query, $user);

        return $query->get(['asset_type', 'asset_id'])
            ->map(fn (MetadataAsset $asset): string => $asset->asset_type.':'.$asset->asset_id)
            ->all();
    }

    private function canViewStat(MetadataUsageStat $stat, ?User $user): bool
    {
        $asset = $this->assetForStat($stat);

        return $asset instanceof MetadataAsset && $this->authorizer->canViewAsset($asset, $user);
    }

    private function assetForStat(MetadataUsageStat $stat): ?MetadataAsset
    {
        return MetadataAsset::query()
            ->where('asset_type', $stat->asset_type)
            ->where('asset_id', $stat->asset_id)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assetPayload(?MetadataAsset $asset): ?array
    {
        if (! $asset instanceof MetadataAsset) {
            return null;
        }

        return [
            'asset_type' => $asset->asset_type,
            'asset_id' => $asset->asset_id,
            'name' => $asset->name,
            'code' => $asset->code,
            'status' => $asset->status,
            'dataset_id' => $asset->dataset_id,
            'data_source_id' => $asset->data_source_id,
        ];
    }
}
