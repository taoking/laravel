<?php

namespace Tests\Feature;

use App\Domains\Files\Models\UploadedFile as UploadedFileRecord;
use App\Domains\Imports\Models\ImportFailure;
use App\Domains\Imports\Models\ImportTask;
use App\Domains\Metrics\Models\MetricValue;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class PhaseSeventeenExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_import_creates_task_processes_rows_and_records_failures(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $taskId = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'xlsx-import-demo-1')
            ->post('/api/v1/imports', ['file' => $this->xlsxFile([
                ['metric_code', 'region_code', 'frequency_code', 'period_date', 'period_label', 'value', 'source'],
                ['revenue_amount', 'CN-SH', 'monthly', '2026-05-01', '2026-05', 1300000, 'xlsx-test'],
                ['missing_metric', 'CN-SH', 'monthly', '2026-05-01', '2026-05', 10, 'xlsx-test'],
            ])], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('data.import_task.status', 'completed_with_errors')
            ->json('data.import_task.id');

        $this->assertDatabaseHas(ImportTask::class, [
            'id' => $taskId,
            'idempotency_key' => 'xlsx-import-demo-1',
            'original_name' => 'metric-values.xlsx',
            'total_rows' => 2,
            'success_rows' => 1,
            'failed_rows' => 1,
        ]);

        $this->assertDatabaseHas(MetricValue::class, [
            'period_date' => '2026-05-01 00:00:00',
            'period_label' => '2026-05',
            'source' => 'xlsx-test',
        ]);

        $this->assertDatabaseHas(ImportFailure::class, [
            'import_task_id' => $taskId,
            'row_number' => 3,
        ]);
        $this->assertDatabaseHas(UploadedFileRecord::class, [
            'original_name' => 'metric-values.xlsx',
            'visibility' => 'private',
        ]);
    }

    public function test_xlsx_import_keeps_idempotency_and_retry_flow(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $first = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'xlsx-import-demo-2')
            ->post('/api/v1/imports', ['file' => $this->validXlsxFile()], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('data.import_task.status', 'completed')
            ->json('data.import_task.id');

        $second = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'xlsx-import-demo-2')
            ->post('/api/v1/imports', ['file' => $this->validXlsxFile()], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.import_task.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ImportTask::query()->where('idempotency_key', 'xlsx-import-demo-2')->count());

        $this->actingAs($admin)
            ->postJson("/api/v1/imports/{$first}/retry")
            ->assertOk()
            ->assertJsonPath('data.import_task.status', 'completed');
    }

    public function test_legacy_xls_files_are_rejected_without_creating_task(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post('/api/v1/imports', [
                'file' => UploadedFile::fake()->create('metric-values.xls', 8, 'application/vnd.ms-excel'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['file'], 'trace_id']);

        $this->assertSame(0, ImportTask::query()->count());
    }

    public function test_openapi_documents_xlsx_import_support(): void
    {
        $this->get('/docs/openapi.yaml')
            ->assertOk()
            ->assertSee('创建 CSV/XLSX 导入任务')
            ->assertSee('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertSee('.xlsx');
    }

    private function validXlsxFile(): UploadedFile
    {
        return $this->xlsxFile([
            ['metric_code', 'region_code', 'frequency_code', 'period_date', 'period_label', 'value', 'source'],
            ['revenue_amount', 'CN-SH', 'monthly', '2026-05-01', '2026-05', 1300000, 'xlsx-test'],
        ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function xlsxFile(array $rows): UploadedFile
    {
        $path = sys_get_temp_dir().'/metric-values-'.Str::uuid().'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return new UploadedFile(
            $path,
            'metric-values.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
