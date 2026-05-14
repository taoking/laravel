<?php

namespace Tests\Feature;

use App\Domains\Files\Models\UploadedFile as UploadedFileRecord;
use App\Domains\Imports\Models\ExportTask;
use App\Domains\Imports\Models\ImportFailure;
use App\Domains\Imports\Models\ImportTask;
use App\Domains\Metrics\Models\MetricValue;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFourImportQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_creates_task_processes_rows_and_records_failures(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $csv = UploadedFile::fake()->createWithContent('metric-values.csv', implode("\n", [
            'metric_code,region_code,frequency_code,period_date,period_label,value,source',
            'revenue_amount,CN-SH,monthly,2026-05-01,2026-05,1300000,test',
            'missing_metric,CN-SH,monthly,2026-05-01,2026-05,10,test',
        ]));

        $taskId = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'import-demo-1')
            ->post('/api/v1/imports', ['file' => $csv], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('data.import_task.status', 'completed_with_errors')
            ->json('data.import_task.id');

        $this->assertDatabaseHas(ImportTask::class, [
            'id' => $taskId,
            'idempotency_key' => 'import-demo-1',
            'total_rows' => 2,
            'success_rows' => 1,
            'failed_rows' => 1,
        ]);

        $this->assertDatabaseHas(MetricValue::class, [
            'period_date' => '2026-05-01 00:00:00',
            'period_label' => '2026-05',
            'source' => 'test',
        ]);

        $this->assertDatabaseHas(ImportFailure::class, [
            'import_task_id' => $taskId,
            'row_number' => 3,
        ]);
        $this->assertDatabaseHas(UploadedFileRecord::class, [
            'original_name' => 'metric-values.csv',
            'visibility' => 'private',
        ]);
    }

    public function test_import_idempotency_key_prevents_duplicate_tasks_and_retry_reprocesses(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $first = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'import-demo-2')
            ->post('/api/v1/imports', ['file' => $this->validCsv()], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->json('data.import_task.id');

        $second = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'import-demo-2')
            ->post('/api/v1/imports', ['file' => $this->validCsv()], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.import_task.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ImportTask::query()->where('idempotency_key', 'import-demo-2')->count());

        $this->actingAs($admin)
            ->postJson("/api/v1/imports/{$first}/retry")
            ->assertOk()
            ->assertJsonPath('data.import_task.status', 'completed');
    }

    public function test_import_and_export_permissions_are_enforced(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $analyst = User::query()->where('email', 'analyst@example.com')->firstOrFail();

        $this->actingAs($analyst)
            ->post('/api/v1/imports', ['file' => $this->validCsv()], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($analyst)
            ->postJson('/api/v1/exports', ['type' => 'metrics'])
            ->assertForbidden();
    }

    public function test_export_task_is_idempotent_and_daily_summary_command_runs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $first = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'export-demo-1')
            ->postJson('/api/v1/exports', ['type' => 'metrics'])
            ->assertAccepted()
            ->json('data.export_task.id');

        $second = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'export-demo-1')
            ->postJson('/api/v1/exports', ['type' => 'metrics'])
            ->assertOk()
            ->json('data.export_task.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ExportTask::query()->where('idempotency_key', 'export-demo-1')->count());

        $this->artisan('metrics:daily-summary')->assertExitCode(0);
    }

    public function test_openapi_documents_phase_four_import_export_endpoints(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('/api/v1/imports')
            ->assertSee('/api/v1/imports/{id}/retry')
            ->assertSee('/api/v1/exports');
    }

    private function validCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('metric-values.csv', implode("\n", [
            'metric_code,region_code,frequency_code,period_date,period_label,value,source',
            'revenue_amount,CN-SH,monthly,2026-05-01,2026-05,1300000,test',
        ]));
    }
}
