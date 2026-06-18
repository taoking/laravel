<?php

namespace App\Modules\DataPermission\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DataPermission\Models\DataPermissionRule;
use App\Modules\DataPermission\Requests\StoreDataPermissionRuleRequest;
use App\Modules\DataPermission\Requests\UpdateDataPermissionRuleRequest;
use App\Modules\DataPermission\Resources\DataPermissionRuleResource;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataPermissionRuleController extends Controller
{
    public function index(Request $request, DataPermissionService $dataPermissionService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $rules = $dataPermissionService->paginateDataRules($pageSize);

        return ApiResponse::paginated(
            $rules,
            DataPermissionRuleResource::collection($rules->items())->resolve($request),
        );
    }

    public function store(StoreDataPermissionRuleRequest $request, DataPermissionService $dataPermissionService): JsonResponse
    {
        $rule = $dataPermissionService->createDataRule($request->validated());

        return ApiResponse::created((new DataPermissionRuleResource($rule))->resolve($request));
    }

    public function show(DataPermissionRule $dataPermissionRule, Request $request): JsonResponse
    {
        return ApiResponse::success((new DataPermissionRuleResource($dataPermissionRule))->resolve($request));
    }

    public function update(UpdateDataPermissionRuleRequest $request, DataPermissionRule $dataPermissionRule, DataPermissionService $dataPermissionService): JsonResponse
    {
        $rule = $dataPermissionService->updateDataRule($dataPermissionRule, $request->validated());

        return ApiResponse::success((new DataPermissionRuleResource($rule))->resolve($request));
    }

    public function destroy(DataPermissionRule $dataPermissionRule, DataPermissionService $dataPermissionService): JsonResponse
    {
        $dataPermissionService->deleteDataRule($dataPermissionRule);

        return ApiResponse::noContent();
    }
}
