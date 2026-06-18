<?php

namespace App\Modules\DataPermission\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DataPermission\Models\ColumnPermissionRule;
use App\Modules\DataPermission\Requests\StoreColumnPermissionRuleRequest;
use App\Modules\DataPermission\Requests\UpdateColumnPermissionRuleRequest;
use App\Modules\DataPermission\Resources\ColumnPermissionRuleResource;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColumnPermissionRuleController extends Controller
{
    public function index(Request $request, DataPermissionService $dataPermissionService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $rules = $dataPermissionService->paginateColumnRules($pageSize);

        return ApiResponse::paginated(
            $rules,
            ColumnPermissionRuleResource::collection($rules->items())->resolve($request),
        );
    }

    public function store(StoreColumnPermissionRuleRequest $request, DataPermissionService $dataPermissionService): JsonResponse
    {
        $rule = $dataPermissionService->createColumnRule($request->validated());

        return ApiResponse::created((new ColumnPermissionRuleResource($rule))->resolve($request));
    }

    public function show(ColumnPermissionRule $columnPermissionRule, Request $request): JsonResponse
    {
        return ApiResponse::success((new ColumnPermissionRuleResource($columnPermissionRule))->resolve($request));
    }

    public function update(UpdateColumnPermissionRuleRequest $request, ColumnPermissionRule $columnPermissionRule, DataPermissionService $dataPermissionService): JsonResponse
    {
        $rule = $dataPermissionService->updateColumnRule($columnPermissionRule, $request->validated());

        return ApiResponse::success((new ColumnPermissionRuleResource($rule))->resolve($request));
    }

    public function destroy(ColumnPermissionRule $columnPermissionRule, DataPermissionService $dataPermissionService): JsonResponse
    {
        $dataPermissionService->deleteColumnRule($columnPermissionRule);

        return ApiResponse::noContent();
    }
}
