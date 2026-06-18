<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Import\Models\ImportTask;
use App\Modules\Import\Models\ImportTaskLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_csv_and_generate_dataset(): void
    {
        config(['filesystems.import_disk' => 'minio']);
        Storage::fake('minio');
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);

        $response = $this->post('/api/import-tasks', [
            'tenant_id' => 10,
            'file' => $this->csvFile(),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.file_name', 'sales.csv')
            ->assertJsonPath('data.file_type', 'csv')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.success_rows', 2)
            ->assertJsonPath('data.failed_rows', 0)
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.created_by', $actor->id)
            ->assertJsonPath('data.uploaded_table.schema_json.1.field_name', 'amount')
            ->assertJsonPath('data.uploaded_table.schema_json.1.normalized_type', 'decimal');

        $task = ImportTask::query()->firstOrFail();
        $uploadedTable = $task->uploadedTable()->firstOrFail();

        Storage::disk('minio')->assertExists($task->file_path);
        $this->assertTrue(Schema::hasTable($uploadedTable->table_name));
        $this->assertSame(2, DB::table($uploadedTable->table_name)->count());
        $this->assertDatabaseHas($uploadedTable->table_name, [
            'province' => 'GD',
            'amount' => 12.5,
            'year' => 2026,
        ]);

        $dataset = Dataset::query()->where('main_table', $uploadedTable->table_name)->firstOrFail();

        $this->assertSame('sales', $dataset->name);
        $this->assertDatabaseHas('dataset_fields', [
            'dataset_id' => $dataset->id,
            'field_name' => 'amount',
            'normalized_type' => 'decimal',
            'is_metric' => true,
            'default_aggregate' => 'sum',
        ]);
        $this->assertDatabaseHas('data_source_fields', [
            'data_source_id' => $dataset->data_source_id,
            'table_name' => $uploadedTable->table_name,
            'field_name' => 'year',
            'normalized_type' => 'integer',
        ]);

        $this->getJson('/api/import-tasks/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.uploaded_table.table_name', $uploadedTable->table_name)
            ->assertJsonPath('data.logs.0.status', 'completed')
            ->assertJsonPath('data.logs_count', 1);

        $this->deleteJson('/api/import-tasks/'.$task->id)
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertDatabaseMissing('import_tasks', ['id' => $task->id]);
        $this->assertFalse(Schema::hasTable($uploadedTable->table_name));
        Storage::disk('minio')->assertMissing($task->file_path);
    }

    public function test_failed_import_task_can_be_retried(): void
    {
        config(['filesystems.import_disk' => 'minio']);
        Storage::fake('minio');
        Sanctum::actingAs(User::factory()->create());

        Storage::disk('minio')->put('imports/retry.csv', $this->csvContents());
        $task = ImportTask::query()->create([
            'file_name' => 'retry.csv',
            'file_path' => 'imports/retry.csv',
            'file_type' => 'csv',
            'file_size' => strlen($this->csvContents()),
            'status' => 'failed',
            'error_message' => 'previous failure',
        ]);
        ImportTaskLog::query()->create([
            'import_task_id' => $task->id,
            'status' => 'failed',
            'message' => 'old failure',
            'created_at' => now(),
        ]);

        $this->postJson("/api/import-tasks/{$task->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.error_message', null)
            ->assertJsonPath('data.logs.0.status', 'completed');

        $task->refresh();
        $uploadedTable = $task->uploadedTable()->firstOrFail();

        $this->assertSame('completed', $task->status);
        $this->assertSame(2, DB::table($uploadedTable->table_name)->count());
        $this->assertDatabaseMissing('import_task_logs', [
            'import_task_id' => $task->id,
            'message' => 'old failure',
        ]);
    }

    public function test_import_task_endpoints_require_authentication(): void
    {
        $this->getJson('/api/import-tasks')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
    }

    private function csvFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_csv_');
        $this->assertIsString($path);
        file_put_contents($path, $this->csvContents());

        return new UploadedFile($path, 'sales.csv', 'text/csv', null, true);
    }

    private function csvContents(): string
    {
        return "province,amount,year\nGD,12.5,2026\nBJ,7,2026\n";
    }
}
