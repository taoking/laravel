<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\PermissionService;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(RoleResource::collection($roles)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:80', 'unique:roles,code'],
            'data_scope' => ['sometimes', Rule::in(['all', 'department', 'self'])],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = DB::transaction(function () use ($validated) {
            $role = Role::query()->create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'data_scope' => $validated['data_scope'] ?? 'self',
                'description' => $validated['description'] ?? null,
            ]);

            $role->permissions()->sync($validated['permission_ids'] ?? []);

            return $role->load('permissions');
        });

        return ApiResponse::success([
            'role' => RoleResource::make($role)->resolve(),
        ], 'Created.', 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($role->is_system, 403, 'System role cannot be changed.');

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'code' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('roles', 'code')->ignore($role->id)],
            'data_scope' => ['sometimes', Rule::in(['all', 'department', 'self'])],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role->fill($validated)->save();

        return ApiResponse::success([
            'role' => RoleResource::make($role->refresh()->load('permissions'))->resolve(),
        ]);
    }

    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        abort_if($role->code === 'super_admin', 403, 'Super admin role owns all permissions.');

        $validated = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permission_ids']);
        $role->load('users')->users->each(fn ($user) => app(PermissionService::class)->clearForUser($user));

        return ApiResponse::success([
            'role' => RoleResource::make($role->refresh()->load('permissions'))->resolve(),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_if($role->is_system, 403, 'System role cannot be deleted.');

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();

            $role->load('users')->users->each(function ($user) use ($role): void {
                $role->users()->detach($user->id);
                app(PermissionService::class)->clearForUser($user);
            });

            $role->delete();
        });

        return ApiResponse::success(message: 'Deleted.');
    }
}
