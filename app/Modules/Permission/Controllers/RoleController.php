<?php

namespace App\Modules\Permission\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Permission\Models\Role;
use App\Modules\Permission\Requests\StoreRoleRequest;
use App\Modules\Permission\Requests\UpdateRoleRequest;
use App\Modules\Permission\Resources\RoleResource;
use App\Modules\Permission\Services\RoleService;
use App\Support\Response\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request, RoleService $roleService): JsonResponse
    {
        $pageSize = min(max($request->integer('page_size', 20), 1), 100);
        $roles = $roleService->paginate($pageSize);

        return ApiResponse::paginated(
            $roles,
            RoleResource::collection($roles->items())->resolve($request),
        );
    }

    public function store(StoreRoleRequest $request, RoleService $roleService): JsonResponse
    {
        $role = $roleService->create($request->validated());

        return ApiResponse::created((new RoleResource($role))->resolve($request));
    }

    public function show(Role $role, Request $request): JsonResponse
    {
        $role->load('permissions');

        return ApiResponse::success((new RoleResource($role))->resolve($request));
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleService $roleService): JsonResponse
    {
        $role = $roleService->update($role, $request->validated());

        return ApiResponse::success((new RoleResource($role))->resolve($request));
    }

    public function destroy(Role $role, RoleService $roleService): JsonResponse
    {
        $roleService->delete($role);

        return ApiResponse::noContent();
    }
}
