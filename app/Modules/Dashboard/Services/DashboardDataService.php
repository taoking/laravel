<?php

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\Chart\Services\ChartDataService;
use App\Modules\Dashboard\Models\Dashboard;
use Illuminate\Validation\ValidationException;

class DashboardDataService
{
    public function __construct(
        private readonly DashboardFilterBuilder $filterBuilder,
        private readonly ChartDataService $chartDataService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{dashboard_id: int, filters: list<array<string, mixed>>, widgets: list<array<string, mixed>>}
     */
    public function data(Dashboard $dashboard, array $payload, ?User $actor): array
    {
        if ($dashboard->status !== 'active') {
            throw ValidationException::withMessages([
                'dashboard' => ['The selected dashboard is disabled.'],
            ]);
        }

        $dashboard->loadMissing(['widgets.chart.dataset.fields', 'filters']);
        $filters = $this->filterBuilder->build($dashboard, $payload);

        $widgets = $dashboard->widgets
            ->map(function ($widget) use ($filters, $actor): array {
                $widgetOverrides = is_array($widget->config_json) ? ($widget->config_json['query_overrides'] ?? []) : [];
                $widgetFilters = $widgetOverrides['filters'] ?? [];
                unset($widgetOverrides['filters']);

                return [
                    'widget_id' => $widget->id,
                    'chart_id' => $widget->chart_id,
                    'widget_type' => $widget->widget_type,
                    'data' => $this->chartDataService->data($widget->chart, [
                        ...$widgetOverrides,
                        'filters' => array_merge($filters, $widgetFilters),
                    ], $actor),
                ];
            })
            ->values()
            ->all();

        return [
            'dashboard_id' => $dashboard->id,
            'filters' => $filters,
            'widgets' => $widgets,
        ];
    }
}
