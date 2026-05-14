<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Menu;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhaseTwoAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_and_api_returns_json_401(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'trace_id']);
    }

    public function test_seeded_super_admin_can_login_and_open_admin_dashboard(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);

        $this->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
    }

    public function test_current_user_and_permission_menu_api_are_available(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('success', true);

        $this->actingAs($admin)
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonPath('data.permissions.0', '*')
            ->assertJsonStructure([
                'data' => [
                    'permissions',
                    'menus',
                ],
            ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/permissions/catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'permissions' => [
                        ['id', 'name', 'code', 'type'],
                    ],
                    'menus' => [
                        ['id', 'title', 'path', 'is_visible'],
                    ],
                ],
            ]);
    }

    public function test_permission_middleware_allows_and_denies_user_list_access(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        $this->actingAs($analyst)
            ->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->actingAs($admin)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_horizontal_access_control_blocks_other_user_detail(): void
    {
        $this->seed(AccessControlSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();
        $other = User::factory()->create();

        $this->actingAs($analyst)
            ->getJson("/api/v1/users/{$analyst->id}")
            ->assertOk()
            ->assertJsonPath('data.user.email', 'analyst@example.com');

        $this->actingAs($analyst)
            ->getJson("/api/v1/users/{$other->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_role_permission_update_refreshes_user_permission_cache(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'User Viewer',
            'code' => 'user_viewer',
            'data_scope' => 'self',
        ]);
        $user->roles()->attach($role);

        $permission = Permission::query()->where('code', 'access.users.view')->firstOrFail();

        $this->actingAs($user)
            ->getJson('/api/v1/users')
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson("/api/v1/roles/{$role->id}/permissions", [
                'permission_ids' => [$permission->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($user)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_management_crud_assigns_roles(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $analystRole = Role::query()->where('code', 'analyst')->firstOrFail();

        $userId = $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'Managed User',
                'email' => 'managed@example.com',
                'password' => 'password123',
                'status' => 'active',
                'role_ids' => [$analystRole->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'managed@example.com')
            ->assertJsonPath('data.user.roles.0.code', 'analyst')
            ->json('data.user.id');

        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$userId}", [
                'name' => 'Managed User Disabled',
                'status' => 'disabled',
                'role_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.status', 'disabled')
            ->assertJsonCount(0, 'data.user.roles');

        $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing(User::class, ['id' => $userId]);
    }

    public function test_role_management_crud_and_delete(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $permission = Permission::query()->where('code', 'metrics.view')->firstOrFail();

        $roleId = $this->actingAs($admin)
            ->postJson('/api/v1/roles', [
                'name' => 'Metric Reviewer',
                'code' => 'metric_reviewer',
                'data_scope' => 'self',
                'description' => 'Reviews metric data.',
                'permission_ids' => [$permission->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.role.code', 'metric_reviewer')
            ->assertJsonPath('data.role.permissions.0.code', 'metrics.view')
            ->json('data.role.id');

        $this->actingAs($admin)
            ->putJson("/api/v1/roles/{$roleId}", [
                'name' => 'Metric Reviewer Updated',
                'data_scope' => 'department',
            ])
            ->assertOk()
            ->assertJsonPath('data.role.data_scope', 'department');

        $this->actingAs($admin)
            ->deleteJson("/api/v1/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted(Role::class, ['id' => $roleId]);

        $systemRole = Role::query()->where('code', 'analyst')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/roles/{$systemRole->id}")
            ->assertForbidden();
    }

    public function test_menu_catalog_can_be_updated_by_admin_only(): void
    {
        $this->seed(AccessControlSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();
        $menu = Menu::query()->where('path', '/admin/metrics')->firstOrFail();

        $this->actingAs($analyst)
            ->putJson("/api/v1/menus/{$menu->id}", [
                'title' => 'Metrics Hidden',
                'sort_order' => 5,
                'is_visible' => false,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson("/api/v1/menus/{$menu->id}", [
                'title' => 'Metrics Updated',
                'sort_order' => 5,
                'is_visible' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.menu.title', 'Metrics Updated')
            ->assertJsonPath('data.menu.sort_order', 5)
            ->assertJsonPath('data.menu.is_visible', false);
    }

    public function test_openapi_documents_phase_two_access_endpoints(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('/api/v1/me')
            ->assertSee('/api/v1/permissions')
            ->assertSee('/api/v1/permissions/catalog')
            ->assertSee('/api/v1/users')
            ->assertSee('/api/v1/roles/{role}')
            ->assertSee('/api/v1/menus/{menu}')
            ->assertSee('/api/v1/roles/{role}/permissions');
    }
}
