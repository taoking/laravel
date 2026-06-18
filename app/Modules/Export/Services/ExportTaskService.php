<?php

namespace App\Modules\Export\Services;

use App\Models\User;
use App\Modules\Export\Jobs\ProcessExportTaskJob;
use App\Modules\Export\Models\ExportTask;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExportTaskService
{
    public function paginate(int $pageSize, ?User $actor): LengthAwarePaginator
    {
        return ExportTask::query()
            ->when($actor !== null, fn ($query) => $query->where('created_by', $actor->id))
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): ExportTask
    {
        $task = ExportTask::query()->create([
            'tenant_id' => $payload['tenant_id'] ?? null,
            'export_type' => $payload['export_type'],
            'source_type' => $payload['source_type'],
            'source_id' => $payload['source_id'],
            'status' => 'pending',
            'created_by' => $actor?->id,
        ]);

        ProcessExportTaskJob::dispatch($task->id);

        return $task->refresh();
    }

    public function show(ExportTask $task, ?User $actor): ExportTask
    {
        $this->assertOwned($task, $actor);

        return $task;
    }

    public function retry(ExportTask $task, ?User $actor): ExportTask
    {
        $this->assertOwned($task, $actor);

        if (in_array($task->status, ['pending', 'processing'], true)) {
            throw ValidationException::withMessages([
                'status' => ['The export task is already pending or processing.'],
            ]);
        }

        $task->forceFill([
            'status' => 'pending',
            'file_name' => null,
            'file_path' => null,
            'file_size' => 0,
            'progress' => 0,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        ProcessExportTaskJob::dispatch($task->id);

        return $task->refresh();
    }

    /**
     * @return array{content: string, file_name: string, mime_type: string}
     */
    public function download(ExportTask $task, ?User $actor): array
    {
        $this->assertOwned($task, $actor);

        if ($task->status !== 'completed' || $task->file_path === null || $task->file_name === null) {
            throw ValidationException::withMessages([
                'status' => ['The export file is not ready.'],
            ]);
        }

        $disk = (string) config('filesystems.export_disk', 'minio');

        if (! Storage::disk($disk)->exists($task->file_path)) {
            throw ValidationException::withMessages([
                'file_path' => ['The export file is missing.'],
            ]);
        }

        return [
            'content' => (string) Storage::disk($disk)->get($task->file_path),
            'file_name' => $task->file_name,
            'mime_type' => $this->mimeType($task->export_type),
        ];
    }

    private function assertOwned(ExportTask $task, ?User $actor): void
    {
        if ($actor === null || (int) $task->created_by !== (int) $actor->id) {
            throw new AuthorizationException;
        }
    }

    private function mimeType(string $exportType): string
    {
        return match ($exportType) {
            'csv' => 'text/csv; charset=UTF-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
