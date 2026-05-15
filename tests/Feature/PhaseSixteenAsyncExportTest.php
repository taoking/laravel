<?php

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use App\Domains\Imports\Models\ExportTask;
use App\Jobs\ProcessMetricExportJob;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class PhaseSixteenAsyncExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_export_job_generates_csv_progress_and_owner_download(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $taskId = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'export-metrics-progress')
            ->postJson('/api/v1/exports', [
                'type' => 'metrics',
                'filters' => ['status' => 'active'],
            ])
            ->assertAccepted()
            ->assertJsonPath('data.export_task.status', 'completed')
            ->assertJsonPath('data.export_task.progress_percentage', 100)
            ->json('data.export_task.id');

        $task = ExportTask::query()->findOrFail($taskId);

        $this->assertSame('completed', $task->status);
        $this->assertGreaterThan(0, $task->total_rows);
        $this->assertSame($task->total_rows, $task->processed_rows);
        $this->assertGreaterThan(0, $task->file_size);
        $this->assertNotNull($task->path);
        Storage::disk('local')->assertExists($task->path);

        $this->actingAs($admin)
            ->getJson("/api/v1/exports/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.export_task.id', $task->id)
            ->assertJsonPath('data.export_task.progress_percentage', 100)
            ->assertJsonPath('data.export_task.download_url', "/api/v1/exports/{$task->id}/download");

        $download = $this->actingAs($admin)->get("/api/v1/exports/{$task->id}/download");

        $download->assertOk();
        $this->assertStringContainsString('text/csv', (string) $download->headers->get('content-type'));
        $this->assertStringContainsString('revenue_amount', $download->streamedContent());
        $this->assertNotNull($task->refresh()->downloaded_at);
    }

    public function test_export_idempotency_key_returns_existing_task_without_duplicates(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $first = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'export-idempotent')
            ->postJson('/api/v1/exports', ['type' => 'metrics'])
            ->assertAccepted()
            ->json('data.export_task.id');

        $second = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'export-idempotent')
            ->postJson('/api/v1/exports', ['type' => 'metrics'])
            ->assertOk()
            ->json('data.export_task.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ExportTask::query()->where('idempotency_key', 'export-idempotent')->count());

        $this->actingAs($admin)
            ->getJson('/api/v1/exports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_export_download_requires_owner_and_completed_file(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $otherAdmin = User::factory()->create([
            'email' => 'export-reviewer@example.com',
            'status' => 'active',
        ]);
        $otherAdmin->roles()->attach($superAdminRole);

        Storage::disk('local')->put('exports/owner-only.csv', "id,code\n1,revenue_amount\n");

        $completed = ExportTask::query()->create([
            'user_id' => $admin->id,
            'idempotency_key' => 'export-owner-only',
            'type' => 'metrics',
            'status' => 'completed',
            'filters' => [],
            'disk' => 'local',
            'path' => 'exports/owner-only.csv',
            'total_rows' => 1,
            'processed_rows' => 1,
            'file_size' => 25,
        ]);

        $this->actingAs($otherAdmin)
            ->getJson("/api/v1/exports/{$completed->id}/download")
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $pending = ExportTask::query()->create([
            'user_id' => $admin->id,
            'idempotency_key' => 'export-not-ready',
            'type' => 'metrics',
            'status' => 'pending',
            'filters' => [],
            'disk' => 'local',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/v1/exports/{$pending->id}/download")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Export file is not ready.');
    }

    public function test_export_job_records_failure_classification(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $task = ExportTask::query()->create([
            'user_id' => $admin->id,
            'idempotency_key' => 'export-missing-disk',
            'type' => 'metrics',
            'status' => 'pending',
            'filters' => [],
            'disk' => 'missing_disk',
        ]);

        try {
            (new ProcessMetricExportJob($task->id))->handle();
            $this->fail('The export job should fail when the target disk is not configured.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Disk [missing_disk]', $exception->getMessage());
        }

        $task->refresh();
        $this->assertSame('failed', $task->status);
        $this->assertSame('storage', $task->failure_type);
        $this->assertSame(1, $task->attempts);
        $this->assertNotNull($task->last_failed_at);
        $this->assertNotNull($task->finished_at);
    }
}
