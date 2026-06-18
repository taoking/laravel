<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Dashboard\Models\Dashboard;

class DashboardFilterBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function build(Dashboard $dashboard, array $payload = []): array
    {
        $configuredFilters = collect($dashboard->global_filters_json ?? [])
            ->filter(fn (mixed $filter): bool => is_array($filter))
            ->values();

        $storedDefaults = $dashboard->filters
            ->map(function ($filter): ?array {
                if ($filter->default_value_json === null) {
                    return null;
                }

                return [
                    'field' => $filter->field_name,
                    'operator' => $filter->config_json['operator'] ?? '=',
                    'value' => $filter->default_value_json,
                ];
            })
            ->filter()
            ->values();

        $runtimeFilters = collect($payload['filters'] ?? [])
            ->filter(fn (mixed $filter): bool => is_array($filter))
            ->values();

        return $configuredFilters
            ->merge($storedDefaults)
            ->merge($runtimeFilters)
            ->values()
            ->all();
    }
}
