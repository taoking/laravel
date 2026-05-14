<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Access\Models\Menu;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Services\PermissionService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve(),
        ]);
    }

    public function permissions(Request $request, PermissionService $permissions): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'permissions' => $user->permissionCodes(),
            'menus' => $permissions->menuTree($user),
        ]);
    }

    public function catalog(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'parent_id' => $permission->parent_id,
                'name' => $permission->name,
                'code' => $permission->code,
                'type' => $permission->type,
                'route_name' => $permission->route_name,
                'uri' => $permission->uri,
                'method' => $permission->method,
                'is_system' => $permission->is_system,
            ])
            ->values()
            ->all();

        $menus = Menu::query()
            ->with('permission:id,code')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'title' => $menu->title,
                'route_name' => $menu->route_name,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'permission' => $menu->permission?->code,
                'sort_order' => $menu->sort_order,
                'is_visible' => $menu->is_visible,
            ])
            ->values()
            ->all();

        return ApiResponse::success([
            'permissions' => $permissions,
            'menus' => $menus,
        ]);
    }
}
