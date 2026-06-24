<?php

namespace App\Modules\Semantic\Services;

use App\Modules\Dashboard\Models\DashboardShare;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Models\MetricDependency;
use App\Modules\Semantic\Models\MetricUsage;

class MetricImpactAnalysisService
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(Metric $metric): array
    {
        $chartIds = MetricUsage::query()
            ->where('metric_id', $metric->id)
            ->where('used_by_type', 'chart')
            ->pluck('used_by_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $dashboardIds = DashboardWidget::query()
            ->whereIn('chart_id', $chartIds)
            ->pluck('dashboard_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $dependentMetrics = MetricDependency::query()
            ->with('metric')
            ->where('depends_on_metric_id', $metric->id)
            ->get()
            ->map(fn (MetricDependency $dependency): ?string => $dependency->metric?->code)
            ->filter()
            ->values()
            ->all();

        $shareCount = DashboardShare::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->count();

        $chartCount = count($chartIds);
        $dashboardCount = count($dashboardIds);

        return [
            'metric' => $metric->code,
            'used_by_charts' => $chartCount,
            'used_by_chart_ids' => $chartIds,
            'used_by_dashboards' => $dashboardCount,
            'used_by_dashboard_ids' => $dashboardIds,
            'public_dashboard_shares' => $shareCount,
            'dependent_metrics' => $dependentMetrics,
            'risk_level' => $this->riskLevel($chartCount, $dashboardCount, $shareCount),
        ];
    }

    private function riskLevel(int $chartCount, int $dashboardCount, int $shareCount): string
    {
        if ($dashboardCount > 0 || $shareCount > 0 || $chartCount > 5) {
            return 'high';
        }

        return $chartCount > 0 ? 'medium' : 'low';
    }
}
