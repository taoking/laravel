<?php

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\Cache\Services\DashboardCacheService;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Models\DashboardFilter;
use App\Modules\Dashboard\Models\DashboardShare;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\Metadata\Services\MetadataChangeGuardService;
use App\Modules\Metadata\Services\MetadataLifecycleService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DashboardService
{
    public function __construct(
        private readonly DashboardCacheService $dashboardCacheService,
        private readonly MetadataLifecycleService $metadataLifecycleService,
        private readonly MetadataChangeGuardService $metadataChangeGuardService,
    ) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return Dashboard::query()
            ->withCount('widgets')
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): Dashboard
    {
        $dashboard = DB::transaction(function () use ($payload, $actor): Dashboard {
            $dashboard = Dashboard::query()->create([
                'tenant_id' => $payload['tenant_id'] ?? null,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'layout_json' => $payload['layout_json'] ?? null,
                'global_filters_json' => $payload['global_filters_json'] ?? null,
                'status' => $payload['status'] ?? 'active',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->syncFilters($dashboard, $payload['filters'] ?? []);

            return $dashboard->load(['widgets.chart', 'filters'])->loadCount('widgets');
        });

        $this->dashboardCacheService->forget($dashboard);
        $this->metadataLifecycleService->sync('dashboard', (int) $dashboard->id);

        return $dashboard;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Dashboard $dashboard, array $payload, ?User $actor): Dashboard
    {
        $updatedDashboard = DB::transaction(function () use ($dashboard, $payload, $actor): Dashboard {
            $dashboard->fill([
                ...$payload,
                'updated_by' => $actor?->id,
            ]);
            $dashboard->save();

            if (array_key_exists('filters', $payload)) {
                $this->syncFilters($dashboard, $payload['filters'] ?? []);
            }

            return $dashboard->refresh()->load(['widgets.chart', 'filters'])->loadCount('widgets');
        });

        $this->dashboardCacheService->forget($updatedDashboard);
        $this->metadataLifecycleService->sync('dashboard', (int) $updatedDashboard->id);

        return $updatedDashboard;
    }

    public function delete(Dashboard $dashboard, ?User $actor = null, bool $force = false): void
    {
        $assetId = (int) $dashboard->id;
        $this->metadataChangeGuardService->guardDelete('dashboard', $assetId, $actor, $force);
        $this->dashboardCacheService->forget($dashboard);

        DB::transaction(function () use ($dashboard): void {
            $dashboard->shares()->delete();
            $dashboard->linkages()->delete();
            $dashboard->filters()->delete();
            $dashboard->widgets()->delete();
            $dashboard->delete();
        });

        $this->metadataLifecycleService->archive('dashboard', $assetId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addWidget(Dashboard $dashboard, array $payload): DashboardWidget
    {
        $widget = DB::transaction(function () use ($dashboard, $payload): DashboardWidget {
            $widget = $dashboard->widgets()->create([
                'chart_id' => $payload['chart_id'],
                'widget_type' => $payload['widget_type'] ?? 'chart',
                'x' => $payload['x'] ?? 0,
                'y' => $payload['y'] ?? 0,
                'w' => $payload['w'] ?? 6,
                'h' => $payload['h'] ?? 4,
                'config_json' => $payload['config_json'] ?? null,
                'sort_order' => $payload['sort_order'] ?? 0,
            ]);

            $this->refreshLayout($dashboard);

            return $widget->load('chart');
        });

        $this->dashboardCacheService->forget($dashboard);
        $this->metadataLifecycleService->sync('dashboard', (int) $dashboard->id);

        return $widget;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateWidget(Dashboard $dashboard, DashboardWidget $widget, array $payload): DashboardWidget
    {
        $this->assertWidgetBelongsToDashboard($dashboard, $widget);

        $updatedWidget = DB::transaction(function () use ($dashboard, $widget, $payload): DashboardWidget {
            $widget->fill($payload);
            $widget->save();

            $this->refreshLayout($dashboard);

            return $widget->refresh()->load('chart');
        });

        $this->dashboardCacheService->forget($dashboard);
        $this->metadataLifecycleService->sync('dashboard', (int) $dashboard->id);

        return $updatedWidget;
    }

    public function deleteWidget(Dashboard $dashboard, DashboardWidget $widget): void
    {
        $this->assertWidgetBelongsToDashboard($dashboard, $widget);

        DB::transaction(function () use ($dashboard, $widget): void {
            $widget->delete();
            $this->refreshLayout($dashboard);
        });

        $this->dashboardCacheService->forget($dashboard);
        $this->metadataLifecycleService->sync('dashboard', (int) $dashboard->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function share(Dashboard $dashboard, array $payload, ?User $actor): DashboardShare
    {
        return DashboardShare::query()->create([
            'dashboard_id' => $dashboard->id,
            'share_token' => Str::random(40),
            'share_type' => $payload['share_type'] ?? 'public',
            'password_hash' => isset($payload['password']) ? Hash::make($payload['password']) : null,
            'expired_at' => $payload['expired_at'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    public function resolveShare(string $token): DashboardShare
    {
        return DashboardShare::query()
            ->with('dashboard.widgets.chart.dataset', 'dashboard.filters')
            ->where('share_token', $token)
            ->where(function ($query): void {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
            })
            ->firstOrFail();
    }

    private function assertWidgetBelongsToDashboard(Dashboard $dashboard, DashboardWidget $widget): void
    {
        if ($widget->dashboard_id !== $dashboard->id) {
            abort(404);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $filters
     */
    private function syncFilters(Dashboard $dashboard, array $filters): void
    {
        DashboardFilter::query()
            ->where('dashboard_id', $dashboard->id)
            ->delete();

        foreach ($filters as $filter) {
            $dashboard->filters()->create([
                'field_name' => $filter['field_name'],
                'label' => $filter['label'],
                'filter_type' => $filter['filter_type'] ?? 'select',
                'default_value_json' => $filter['default_value_json'] ?? null,
                'config_json' => $filter['config_json'] ?? null,
            ]);
        }
    }

    private function refreshLayout(Dashboard $dashboard): void
    {
        $layout = $dashboard->widgets()
            ->get()
            ->map(fn (DashboardWidget $widget): array => [
                'widget_id' => $widget->id,
                'x' => $widget->x,
                'y' => $widget->y,
                'w' => $widget->w,
                'h' => $widget->h,
            ])
            ->values()
            ->all();

        $dashboard->forceFill([
            'layout_json' => $layout,
        ])->save();
    }
}
