<?php

namespace App\Modules\Permission\Services;

use App\Modules\Cache\Services\PermissionCacheService;
use App\Modules\Permission\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(private readonly PermissionCacheService $permissionCacheService) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Role
    {
        return DB::transaction(function () use ($payload): Role {
            $permissionIds = Arr::pull($payload, 'permission_ids', []);
            $payload['guard_name'] ??= 'sanctum';
            $payload['is_system'] ??= false;

            $role = Role::query()->create($payload);
            $role->permissions()->sync($permissionIds);

            return $role->load('permissions');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Role $role, array $payload): Role
    {
        $this->permissionCacheService->forgetUsersForRole($role);

        return DB::transaction(function () use ($role, $payload): Role {
            $shouldSyncPermissions = array_key_exists('permission_ids', $payload);
            $permissionIds = Arr::pull($payload, 'permission_ids', []);

            $role->fill($payload);
            $role->save();

            if ($shouldSyncPermissions) {
                $role->permissions()->sync($permissionIds);
            }

            return $role->load('permissions');
        });
    }

    public function delete(Role $role): void
    {
        $this->permissionCacheService->forgetUsersForRole($role);

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        });
    }
}
