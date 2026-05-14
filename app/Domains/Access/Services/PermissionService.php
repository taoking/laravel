<?php

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    private const CACHE_TTL_SECONDS = 1800;

    public function permissionCodes(User $user): array
    {
        return Cache::remember($this->permissionCacheKey($user), self::CACHE_TTL_SECONDS, function () use ($user) {
            return $user->roles()
                ->with('permissions:id,code')
                ->get()
                ->flatMap(fn ($role) => $role->permissions->pluck('code'))
                ->unique()
                ->values()
                ->all();
        });
    }

    public function menuTree(User $user): array
    {
        return Cache::remember($this->menuCacheKey($user), self::CACHE_TTL_SECONDS, function () use ($user) {
            $permissionCodes = $this->permissionCodes($user);

            $menus = Menu::query()
                ->with('permission:id,code')
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->get()
                ->filter(function (Menu $menu) use ($user, $permissionCodes) {
                    return $user->isSuperAdmin()
                        || $menu->permission_id === null
                        || in_array($menu->permission?->code, $permissionCodes, true);
                })
                ->values();

            return $this->buildMenuTree($menus);
        });
    }

    public function clearForUser(User $user): void
    {
        Cache::forget($this->permissionCacheKey($user));
        Cache::forget($this->menuCacheKey($user));
    }

    private function permissionCacheKey(User $user): string
    {
        return "access:user:{$user->id}:permissions";
    }

    private function menuCacheKey(User $user): string
    {
        return "access:user:{$user->id}:menus";
    }

    private function buildMenuTree(Collection $menus, ?int $parentId = null): array
    {
        return $menus
            ->filter(fn (Menu $menu) => $menu->parent_id === $parentId)
            ->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'title' => $menu->title,
                'path' => $menu->path,
                'route_name' => $menu->route_name,
                'icon' => $menu->icon,
                'permission' => $menu->permission?->code,
                'children' => $this->buildMenuTree($menus, $menu->id),
            ])
            ->values()
            ->all();
    }
}
