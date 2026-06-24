<?php

namespace App\Modules\DataPermission\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DataPermission\DTO\PermissionContext;
use App\Modules\DataPermission\Services\PermissionCompiler;
use App\Modules\Dataset\Models\Dataset;
use App\Support\Auth\AdminAuthorizer;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionDebugController extends Controller
{
    public function __invoke(Request $request, PermissionCompiler $compiler, AdminAuthorizer $authorizer): JsonResponse
    {
        $authorizer->assertAdmin($request->user());

        $payload = $request->validate([
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'chart_id' => ['nullable', 'integer', 'exists:charts,id'],
            'dashboard_id' => ['nullable', 'integer', 'exists:dashboards,id'],
        ]);
        $dataset = Dataset::query()->with('fields')->findOrFail($payload['dataset_id']);

        return ApiResponse::success($compiler->compile(new PermissionContext(
            dataset: $dataset,
            user: $request->user(),
            requestSource: 'permission_debug',
            chartId: isset($payload['chart_id']) ? (int) $payload['chart_id'] : null,
            dashboardId: isset($payload['dashboard_id']) ? (int) $payload['dashboard_id'] : null,
        ))->toArray());
    }
}
