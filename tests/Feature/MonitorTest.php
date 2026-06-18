<?php

namespace Tests\Feature;

use App\Modules\Export\Models\ExportTask;
use App\Modules\Import\Models\ImportTask;
use App\Modules\Query\Models\QueryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoints_are_accessible(): void
    {
        config(['filesystems.export_disk' => 'local']);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.checks.database.status', 'ok');

        $this->getJson('/api/health/database')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');

        $this->getJson('/api/health/storage')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');

        $this->getJson('/api/health/queue')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.pending_jobs', 0);

        $this->getJson('/api/health/redis')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    public function test_prometheus_metrics_include_query_task_and_queue_counters(): void
    {
        QueryLog::query()->create([
            'query_hash' => 'success-hash',
            'sql' => 'select 1',
            'elapsed_ms' => 12,
            'row_count' => 1,
            'cached' => true,
            'status' => 'success',
        ]);
        QueryLog::query()->create([
            'query_hash' => 'failed-hash',
            'sql' => 'select failed',
            'elapsed_ms' => 20,
            'row_count' => 0,
            'cached' => false,
            'status' => 'failed',
            'error_message' => 'failure',
        ]);
        ImportTask::query()->create([
            'file_name' => 'sales.csv',
            'file_path' => 'imports/sales.csv',
            'file_type' => 'csv',
            'status' => 'completed',
        ]);
        ExportTask::query()->create([
            'export_type' => 'csv',
            'source_type' => 'chart',
            'source_id' => 1,
            'status' => 'completed',
        ]);

        $response = $this->get('/api/metrics')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; version=0.0.4; charset=UTF-8');

        $content = $response->content();

        $this->assertStringContainsString('bi_query_total 2', $content);
        $this->assertStringContainsString('bi_query_failed_total 1', $content);
        $this->assertStringContainsString('bi_query_duration_ms 32', $content);
        $this->assertStringContainsString('bi_query_cache_hit_total 1', $content);
        $this->assertStringContainsString('bi_import_task_total 1', $content);
        $this->assertStringContainsString('bi_export_task_total 1', $content);
        $this->assertStringContainsString('bi_queue_pending_jobs 0', $content);
    }
}
