<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Permission\Models\Permission;
use App\Modules\Permission\Models\Role;
use App\Modules\User\Models\Department;
use App\Modules\User\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['code' => 'default'],
            [
                'name' => 'Default Organization',
                'status' => 'active',
            ],
        );

        $department = Department::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'code' => 'headquarters',
            ],
            [
                'name' => 'Headquarters',
                'status' => 'active',
                'sort_order' => 0,
            ],
        );

        $permissionDefinitions = [
            ['name' => 'View users', 'code' => 'users.view', 'group' => 'users'],
            ['name' => 'Create users', 'code' => 'users.create', 'group' => 'users'],
            ['name' => 'Update users', 'code' => 'users.update', 'group' => 'users'],
            ['name' => 'Delete users', 'code' => 'users.delete', 'group' => 'users'],
            ['name' => 'View roles', 'code' => 'roles.view', 'group' => 'roles'],
            ['name' => 'Create roles', 'code' => 'roles.create', 'group' => 'roles'],
            ['name' => 'Update roles', 'code' => 'roles.update', 'group' => 'roles'],
            ['name' => 'Delete roles', 'code' => 'roles.delete', 'group' => 'roles'],
            ['name' => 'View permissions', 'code' => 'permissions.view', 'group' => 'permissions'],
            ['name' => 'Create permissions', 'code' => 'permissions.create', 'group' => 'permissions'],
            ['name' => 'Update permissions', 'code' => 'permissions.update', 'group' => 'permissions'],
            ['name' => 'Delete permissions', 'code' => 'permissions.delete', 'group' => 'permissions'],
        ];

        $permissions = collect($permissionDefinitions)->map(fn (array $definition): Permission => Permission::query()->updateOrCreate(
            ['code' => $definition['code']],
            [
                'name' => $definition['name'],
                'guard_name' => 'sanctum',
                'group' => $definition['group'],
            ],
        ));

        $adminRole = Role::query()->updateOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Administrator',
                'guard_name' => 'sanctum',
                'description' => 'Full access to Phase 1 management APIs.',
                'is_system' => true,
            ],
        );

        $adminRole->permissions()->sync($permissions->pluck('id')->all());

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'organization_id' => $organization->id,
                'department_id' => $department->id,
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
