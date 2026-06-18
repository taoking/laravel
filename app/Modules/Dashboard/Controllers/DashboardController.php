<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\Dashboard\Requests\DashboardDataRequest;
use App\Modules\Dashboard\Requests\ShareDashboardRequest;
use App\Modules\Dashboard\Requests\StoreDashboardRequest;
use App\Modules\Dashboard\Requests\StoreDashboardWidgetRequest;
use App\Modules\Dashboard\Requests\UpdateDashboardRequest;
use App\Modules\Dashboard\Requests\UpdateDashboardWidgetRequest;
use App\Modules\Dashboard\Resources\DashboardResource;
use App\Modules\Dashboard\Resources\DashboardShareResource;
use App\Modules\Dashboard\Resources\DashboardWidgetResource;
use App\Modules\Dashboard\Services\DashboardDataService;
use App\Modules\Dashboard\Services\DashboardService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $dashboards = $dashboardService->paginate($pageSize);

        return ApiResponse::paginated(
            $dashboards,
            DashboardResource::collection($dashboards->items())->resolve($request),
        );
    }

    public function store(StoreDashboardRequest $request, DashboardService $dashboardService): JsonResponse
    {
        $dashboard = $dashboardService->create($request->validated(), $request->user());

        return ApiResponse::created((new DashboardResource($dashboard))->resolve($request));
    }

    public function show(Dashboard $dashboard, Request $request): JsonResponse
    {
        $dashboard->load(['widgets.chart', 'filters'])->loadCount('widgets');

        return ApiResponse::success((new DashboardResource($dashboard))->resolve($request));
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard, DashboardService $dashboardService): JsonResponse
    {
        $dashboard = $dashboardService->update($dashboard, $request->validated(), $request->user());

        return ApiResponse::success((new DashboardResource($dashboard))->resolve($request));
    }

    public function destroy(Dashboard $dashboard, DashboardService $dashboardService): JsonResponse
    {
        $dashboardService->delete($dashboard);

        return ApiResponse::noContent();
    }

    public function storeWidget(StoreDashboardWidgetRequest $request, Dashboard $dashboard, DashboardService $dashboardService): JsonResponse
    {
        $widget = $dashboardService->addWidget($dashboard, $request->validated());

        return ApiResponse::created((new DashboardWidgetResource($widget))->resolve($request));
    }

    public function updateWidget(UpdateDashboardWidgetRequest $request, Dashboard $dashboard, DashboardWidget $widget, DashboardService $dashboardService): JsonResponse
    {
        $widget = $dashboardService->updateWidget($dashboard, $widget, $request->validated());

        return ApiResponse::success((new DashboardWidgetResource($widget))->resolve($request));
    }

    public function destroyWidget(Dashboard $dashboard, DashboardWidget $widget, DashboardService $dashboardService): JsonResponse
    {
        $dashboardService->deleteWidget($dashboard, $widget);

        return ApiResponse::noContent();
    }

    public function data(DashboardDataRequest $request, Dashboard $dashboard, DashboardDataService $dashboardDataService): JsonResponse
    {
        return ApiResponse::success($dashboardDataService->data($dashboard, $request->validated(), $request->user()));
    }

    public function share(ShareDashboardRequest $request, Dashboard $dashboard, DashboardService $dashboardService): JsonResponse
    {
        $share = $dashboardService->share($dashboard, $request->validated(), $request->user());

        return ApiResponse::created((new DashboardShareResource($share))->resolve($request));
    }

    public function publicShow(string $token, Request $request, DashboardService $dashboardService): JsonResponse
    {
        $share = $dashboardService->resolveShare($token);

        if ($share->password_hash !== null && ! Hash::check((string) $request->query('password'), $share->password_hash)) {
            return ApiResponse::error(40300, 'Forbidden.', status: 403);
        }

        return ApiResponse::success([
            'share' => (new DashboardShareResource($share))->resolve($request),
            'dashboard' => (new DashboardResource($share->dashboard))->resolve($request),
        ]);
    }
}
