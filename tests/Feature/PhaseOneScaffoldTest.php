<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhaseOneScaffoldTest extends TestCase
{
    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_admin_dashboard_requires_authentication(): void
    {
        $this->get('/admin')
            ->assertRedirect('/login');
    }

    public function test_api_health_uses_standard_json_contract(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Trace-Id')
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'OK')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'service',
                    'version',
                    'timestamp',
                ],
                'trace_id',
            ]);
    }

    public function test_api_errors_use_standard_json_contract(): void
    {
        $this->getJson('/api/v1/missing')
            ->assertNotFound()
            ->assertHeader('X-Trace-Id')
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
                'trace_id',
            ]);
    }

    public function test_api_documentation_routes_are_available(): void
    {
        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('/docs/openapi.yaml', false);

        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi: 3.1.0');
    }
}
