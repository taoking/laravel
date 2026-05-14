<?php

namespace Database\Seeders;

use App\Domains\Access\Models\Menu;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'View dashboard', 'code' => 'access.dashboard.view', 'type' => 'menu', 'route_name' => 'admin.dashboard', 'uri' => '/admin', 'method' => 'GET', 'sort_order' => 10],
            ['name' => 'View users', 'code' => 'access.users.view', 'type' => 'api', 'route_name' => 'api.v1.users.index', 'uri' => '/api/v1/users', 'method' => 'GET', 'sort_order' => 20],
            ['name' => 'Manage users', 'code' => 'access.users.manage', 'type' => 'button', 'route_name' => 'api.v1.users.store', 'uri' => '/api/v1/users', 'method' => 'POST', 'sort_order' => 30],
            ['name' => 'View roles', 'code' => 'access.roles.view', 'type' => 'api', 'route_name' => 'api.v1.roles.index', 'uri' => '/api/v1/roles', 'method' => 'GET', 'sort_order' => 40],
            ['name' => 'Manage roles', 'code' => 'access.roles.manage', 'type' => 'button', 'route_name' => 'api.v1.roles.store', 'uri' => '/api/v1/roles', 'method' => 'POST', 'sort_order' => 50],
            ['name' => 'View menus', 'code' => 'access.menus.view', 'type' => 'menu', 'route_name' => 'admin.menus', 'uri' => '/admin/menus', 'method' => 'GET', 'sort_order' => 60],
            ['name' => 'Manage menus', 'code' => 'access.menus.manage', 'type' => 'button', 'route_name' => 'api.v1.menus.update', 'uri' => '/api/v1/menus/{menu}', 'method' => 'PUT', 'sort_order' => 65],
            ['name' => 'View metrics', 'code' => 'metrics.view', 'type' => 'menu', 'route_name' => 'admin.metrics', 'uri' => '/admin/metrics', 'method' => 'GET', 'sort_order' => 70],
            ['name' => 'Manage metrics', 'code' => 'metrics.manage', 'type' => 'button', 'route_name' => 'api.v1.metrics.store', 'uri' => '/api/v1/metrics', 'method' => 'POST', 'sort_order' => 80],
            ['name' => 'View imports', 'code' => 'imports.view', 'type' => 'menu', 'route_name' => 'admin.imports', 'uri' => '/admin/imports', 'method' => 'GET', 'sort_order' => 90],
            ['name' => 'Manage imports', 'code' => 'imports.manage', 'type' => 'button', 'route_name' => 'api.v1.imports.store', 'uri' => '/api/v1/imports', 'method' => 'POST', 'sort_order' => 100],
            ['name' => 'Manage exports', 'code' => 'exports.manage', 'type' => 'button', 'route_name' => 'api.v1.exports.store', 'uri' => '/api/v1/exports', 'method' => 'POST', 'sort_order' => 110],
            ['name' => 'View audit logs', 'code' => 'audit.view', 'type' => 'menu', 'route_name' => 'admin.audit-logs', 'uri' => '/admin/audit-logs', 'method' => 'GET', 'sort_order' => 120],
        ])->mapWithKeys(function (array $attributes) {
            $permission = Permission::query()->updateOrCreate(
                ['code' => $attributes['code']],
                $attributes + ['is_system' => true],
            );

            return [$permission->code => $permission];
        });

        $dashboardMenu = Menu::query()->updateOrCreate(
            ['path' => '/admin'],
            [
                'permission_id' => $permissions['access.dashboard.view']->id,
                'title' => 'Dashboard',
                'route_name' => 'admin.dashboard',
                'icon' => 'layout-dashboard',
                'sort_order' => 10,
                'is_visible' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['path' => '/admin/users'],
            [
                'parent_id' => $dashboardMenu->id,
                'permission_id' => $permissions['access.users.view']->id,
                'title' => 'Users',
                'route_name' => 'admin.users',
                'icon' => 'users',
                'sort_order' => 20,
                'is_visible' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['path' => '/admin/roles'],
            [
                'parent_id' => $dashboardMenu->id,
                'permission_id' => $permissions['access.roles.view']->id,
                'title' => 'Roles',
                'route_name' => 'admin.roles',
                'icon' => 'shield',
                'sort_order' => 30,
                'is_visible' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['path' => '/admin/metrics'],
            [
                'permission_id' => $permissions['metrics.view']->id,
                'title' => 'Metrics',
                'route_name' => 'admin.metrics',
                'icon' => 'bar-chart',
                'sort_order' => 40,
                'is_visible' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['path' => '/admin/imports'],
            [
                'permission_id' => $permissions['imports.view']->id,
                'title' => 'Imports',
                'route_name' => 'admin.imports',
                'icon' => 'upload',
                'sort_order' => 50,
                'is_visible' => true,
            ],
        );

        Menu::query()->updateOrCreate(
            ['path' => '/admin/audit-logs'],
            [
                'permission_id' => $permissions['audit.view']->id,
                'title' => 'Audit',
                'route_name' => 'admin.audit-logs',
                'icon' => 'file-search',
                'sort_order' => 60,
                'is_visible' => true,
            ],
        );

        $superAdmin = Role::query()->updateOrCreate(
            ['code' => 'super_admin'],
            [
                'name' => 'Super Administrator',
                'data_scope' => 'all',
                'description' => 'Built-in role with all permissions.',
                'is_system' => true,
            ],
        );

        $analyst = Role::query()->updateOrCreate(
            ['code' => 'analyst'],
            [
                'name' => 'Analyst',
                'data_scope' => 'self',
                'description' => 'Can read dashboard and metric menus only.',
                'is_system' => true,
            ],
        );

        $analyst->permissions()->sync([
            $permissions['access.dashboard.view']->id,
            $permissions['metrics.view']->id,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'status' => 'active',
            ],
        );

        $admin->roles()->syncWithoutDetaching([$superAdmin->id]);

        $demo = User::query()->updateOrCreate(
            ['email' => 'analyst@example.com'],
            [
                'name' => 'Analyst User',
                'password' => 'password',
                'status' => 'active',
            ],
        );

        $demo->roles()->syncWithoutDetaching([$analyst->id]);
    }
}
