<?php

namespace App\Modules\DataPermission\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DataPermission\Models\ResourcePermission;
use App\Modules\DataPermission\Requests\StoreResourcePermissionRequest;
use App\Modules\DataPermission\Requests\UpdateResourcePermissionRequest;
use App\Modules\DataPermission\Resources\ResourcePermissionResource;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourcePermissionController extends Controller
{
    public function index(Request $request, DataPermissionService $dataPermissionService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $permissions = $dataPermissionService->paginateResourcePermissions($pageSize);

        return ApiResponse::paginated(
            $permissions,
            ResourcePermissionResource::collection($permissions->items())->resolve($request),
        );
    }

    public function store(StoreResourcePermissionRequest $request, DataPermissionService $dataPermissionService): JsonResponse
    {
        $permission = $dataPermissionService->createResourcePermission($request->validated());

        return ApiResponse::created((new ResourcePermissionResource($permission))->resolve($request));
    }

    public function show(ResourcePermission $resourcePermission, Request $request): JsonResponse
    {
        return ApiResponse::success((new ResourcePermissionResource($resourcePermission))->resolve($request));
    }

    public function update(UpdateResourcePermissionRequest $request, ResourcePermission $resourcePermission, DataPermissionService $dataPermissionService): JsonResponse
    {
        $permission = $dataPermissionService->updateResourcePermission($resourcePermission, $request->validated());

        return ApiResponse::success((new ResourcePermissionResource($permission))->resolve($request));
    }

    public function destroy(ResourcePermission $resourcePermission, DataPermissionService $dataPermissionService): JsonResponse
    {
        $dataPermissionService->deleteResourcePermission($resourcePermission);

        return ApiResponse::noContent();
    }
}
