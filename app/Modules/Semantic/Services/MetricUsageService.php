<?php

namespace App\Modules\Semantic\Services;

use App\Modules\Chart\Models\Chart;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Models\MetricUsage;

class MetricUsageService
{
    public function syncChart(Chart $chart): void
    {
        MetricUsage::query()
            ->where('used_by_type', 'chart')
            ->where('used_by_id', $chart->id)
            ->delete();

        $codes = $this->metricCodesFromConfig($chart->config_json ?? []);

        if ($codes === []) {
            return;
        }

        Metric::query()
            ->where('dataset_id', $chart->dataset_id)
            ->whereIn('code', $codes)
            ->get()
            ->each(function (Metric $metric) use ($chart): void {
                MetricUsage::query()->updateOrCreate(
                    [
                        'metric_id' => $metric->id,
                        'used_by_type' => 'chart',
                        'used_by_id' => $chart->id,
                        'usage_context' => 'chart_config',
                    ],
                    [
                        'metric_version' => $metric->version,
                    ],
                );
            });
    }

    public function forgetChart(Chart $chart): void
    {
        MetricUsage::query()
            ->where('used_by_type', 'chart')
            ->where('used_by_id', $chart->id)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function metricCodesFromConfig(array $config): array
    {
        return collect($config['semantic_metrics'] ?? [])
            ->map(fn (mixed $metric): ?string => is_array($metric) ? ($metric['metric_code'] ?? null) : (is_string($metric) ? $metric : null))
            ->filter(fn (?string $code): bool => $code !== null && $code !== '')
            ->unique()
            ->values()
            ->all();
    }
}
