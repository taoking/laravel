<?php

namespace App\Modules\Permission\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Permission\Models\Permission;
use App\Modules\Permission\Requests\StorePermissionRequest;
use App\Modules\Permission\Requests\UpdatePermissionRequest;
use App\Modules\Permission\Resources\PermissionResource;
use App\Modules\Permission\Services\PermissionService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request, PermissionService $permissionService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $permissions = $permissionService->paginate($pageSize);

        return ApiResponse::paginated(
            $permissions,
            PermissionResource::collection($permissions->items())->resolve($request),
        );
    }

    public function store(StorePermissionRequest $request, PermissionService $permissionService): JsonResponse
    {
        $permission = $permissionService->create($request->validated());

        return ApiResponse::created((new PermissionResource($permission))->resolve($request));
    }

    public function show(Permission $permission, Request $request): JsonResponse
    {
        return ApiResponse::success((new PermissionResource($permission))->resolve($request));
    }

    public function update(UpdatePermissionRequest $request, Permission $permission, PermissionService $permissionService): JsonResponse
    {
        $permission = $permissionService->update($permission, $request->validated());

        return ApiResponse::success((new PermissionResource($permission))->resolve($request));
    }

    public function destroy(Permission $permission, PermissionService $permissionService): JsonResponse
    {
        $permissionService->delete($permission);

        return ApiResponse::noContent();
    }
}
