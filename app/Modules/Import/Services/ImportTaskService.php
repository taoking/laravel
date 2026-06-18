<?php

namespace App\Modules\Import\Services;

use App\Models\User;
use App\Modules\Import\Jobs\ProcessImportTaskJob;
use App\Modules\Import\Models\ImportTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ImportTaskService
{
    public function __construct(private readonly FileUploadService $fileUploadService) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return ImportTask::query()
            ->with('uploadedTable')
            ->withCount('logs')
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): ImportTask
    {
        $storedFile = $this->fileUploadService->store($payload['file']);

        $task = ImportTask::query()->create([
            'tenant_id' => $payload['tenant_id'] ?? null,
            'file_name' => $storedFile['name'],
            'file_path' => $storedFile['path'],
            'file_type' => $storedFile['type'],
            'file_size' => $storedFile['size'],
            'status' => 'pending',
            'created_by' => $actor?->id,
        ]);

        ProcessImportTaskJob::dispatch($task->id);

        return $this->loadForResponse($task->refresh());
    }

    public function show(ImportTask $task): ImportTask
    {
        return $this->loadForResponse($task);
    }

    public function retry(ImportTask $task): ImportTask
    {
        if (in_array($task->status, ['pending', 'processing'], true)) {
            throw ValidationException::withMessages([
                'status' => ['The import task is already pending or processing.'],
            ]);
        }

        $task->logs()->delete();
        $task->forceFill([
            'status' => 'pending',
            'total_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'progress' => 0,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        ProcessImportTaskJob::dispatch($task->id);

        return $this->loadForResponse($task->refresh());
    }

    public function delete(ImportTask $task): void
    {
        $task->loadMissing('uploadedTable');

        if ($task->uploadedTable !== null) {
            Schema::dropIfExists($task->uploadedTable->table_name);
        }

        $this->fileUploadService->delete($task->file_path);
        $task->delete();
    }

    private function loadForResponse(ImportTask $task): ImportTask
    {
        return $task
            ->load([
                'uploadedTable',
                'logs' => fn ($query) => $query->latest('id')->limit(50),
            ])
            ->loadCount('logs');
    }
}
