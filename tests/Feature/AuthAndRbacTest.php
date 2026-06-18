<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_fetch_profile_with_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'email',
                        'roles',
                    ],
                ],
            ]);

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_authenticated_user_can_manage_permissions_roles_and_user_role_binding(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $permissionId = $this->postJson('/api/permissions', [
            'name' => 'View Users',
            'code' => 'users.view',
            'group' => 'users',
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->json('data.id');

        $roleId = $this->postJson('/api/roles', [
            'name' => 'Analyst',
            'code' => 'analyst',
            'permission_ids' => [$permissionId],
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.permissions.0.id', $permissionId)
            ->json('data.id');

        $userId = $this->postJson('/api/users', [
            'name' => 'BI User',
            'email' => 'bi-user@example.com',
            'password' => 'secret-password',
            'role_ids' => [$roleId],
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.roles.0.id', $roleId)
            ->json('data.id');

        $this->assertDatabaseHas('permission_role', [
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        $this->assertDatabaseHas('role_user', [
            'role_id' => $roleId,
            'user_id' => $userId,
        ]);

        $this->putJson("/api/users/{$userId}", [
            'role_ids' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.roles', []);

        $this->assertDatabaseMissing('role_user', [
            'role_id' => $roleId,
            'user_id' => $userId,
        ]);
    }

    public function test_user_index_requires_authentication(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
    }

    public function test_role_index_returns_paginated_response(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Role::query()->create([
            'name' => 'Analyst',
            'code' => 'analyst',
            'guard_name' => 'sanctum',
        ]);

        $this->getJson('/api/roles?page_size=10')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => [
                        'page',
                        'page_size',
                        'total',
                    ],
                ],
            ]);
    }
}
