<?php

namespace Tests\Feature;

use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricValue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseNineDatabasePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_metric_dataset_command_supports_dry_run_and_batch_generation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('metrics:seed-large-dataset', [
            '--rows' => 25,
            '--metrics' => 5,
            '--batch' => 10,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, MetricValue::query()->where('source', 'large-dataset-lab')->count());

        $this->artisan('metrics:seed-large-dataset', [
            '--rows' => 25,
            '--metrics' => 5,
            '--batch' => 10,
        ])->assertExitCode(0);

        $this->assertSame(25, MetricValue::query()->where('source', 'large-dataset-lab')->count());
        $this->assertSame(5, Metric::query()->where('code', 'like', 'perf_metric_%')->count());
    }

    public function test_explain_and_seek_pagination_commands_are_executable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('metrics:seed-large-dataset', [
            '--rows' => 12,
            '--metrics' => 3,
            '--batch' => 6,
        ])->assertExitCode(0);

        $this->artisan('metrics:explain-query', [
            '--region-code' => 'PERF-CN',
            '--frequency-code' => 'perf_daily',
            '--date-from' => '2024-01-01',
            '--limit' => 5,
        ])->assertExitCode(0);

        $this->artisan('metrics:seek-page', [
            '--limit' => 2,
        ])->assertExitCode(0);
    }
}
