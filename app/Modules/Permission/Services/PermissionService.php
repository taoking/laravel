<?php

namespace App\Modules\Permission\Services;

use App\Modules\Cache\Services\PermissionCacheService;
use App\Modules\Permission\Models\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function __construct(private readonly PermissionCacheService $permissionCacheService) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return Permission::query()
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Permission
    {
        $payload['guard_name'] ??= 'sanctum';

        return Permission::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Permission $permission, array $payload): Permission
    {
        $this->permissionCacheService->forgetUsersForPermission($permission);

        $permission->fill($payload);
        $permission->save();

        $this->permissionCacheService->forgetUsersForPermission($permission);

        return $permission->refresh();
    }

    public function delete(Permission $permission): void
    {
        $this->permissionCacheService->forgetUsersForPermission($permission);

        DB::transaction(function () use ($permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });
    }
}
