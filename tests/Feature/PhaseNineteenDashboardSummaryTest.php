<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use App\Domains\Dashboard\Services\DashboardSummaryService;
use App\Domains\Imports\Models\ImportTask;
use App\Domains\Metrics\Models\Metric;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhaseNineteenDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_uses_real_counts_and_invalidates_cache(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        Cache::forget(DashboardSummaryService::CACHE_KEY);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('summary.users', User::query()->count())
                ->where('summary.roles', Role::query()->count())
                ->where('summary.metrics', Metric::query()->count())
                ->where('summary.import_tasks', 0));

        $this->assertTrue(Cache::has(DashboardSummaryService::CACHE_KEY));

        ImportTask::query()->create([
            'user_id' => $admin->id,
            'idempotency_key' => 'dashboard-summary-import',
            'original_name' => 'dashboard-summary.csv',
            'disk' => 'local',
            'path' => 'imports/dashboard-summary.csv',
            'status' => 'pending',
        ]);

        $this->assertFalse(Cache::has(DashboardSummaryService::CACHE_KEY));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('summary.import_tasks', ImportTask::query()->count()));
    }
}
