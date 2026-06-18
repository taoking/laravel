<?php

namespace App\Modules\Cache\Services;

use App\Models\User;
use App\Modules\Permission\Models\Permission;
use App\Modules\Permission\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PermissionCacheService
{
    public function __construct(private readonly CacheKeyBuilder $keyBuilder) {}

    /**
     * @return Collection<int, Permission>
     */
    public function rememberUserPermissions(User $user): Collection
    {
        return Cache::remember(
            $this->keyBuilder->userPermissions((int) $user->id),
            now()->addMinutes(30),
            fn (): Collection => Permission::query()
                ->whereHas('roles.users', fn ($query) => $query->whereKey($user->getKey()))
                ->orderBy('group')
                ->orderBy('code')
                ->get(),
        );
    }

    public function forgetUser(int $userId): void
    {
        Cache::forget($this->keyBuilder->userPermissions($userId));
        Cache::forget($this->keyBuilder->userDataPermissions($userId));
    }

    public function forgetUsersForRole(Role $role): void
    {
        $role->users()
            ->pluck('users.id')
            ->each(fn (int $userId) => $this->forgetUser($userId));
    }

    public function forgetUsersForPermission(Permission $permission): void
    {
        $permission->roles()
            ->with('users:id')
            ->get()
            ->flatMap(fn (Role $role) => $role->users->pluck('id'))
            ->unique()
            ->each(fn (int $userId) => $this->forgetUser($userId));
    }
}
