<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\ExportLog;
use App\Modules\Export\Models\ExportTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExportLogService
{
    public function record(ExportTask $task, string $status): void
    {
        ExportLog::query()->create([
            'tenant_id' => $task->tenant_id,
            'user_id' => $task->created_by,
            'export_task_id' => $task->id,
            'source_type' => $task->source_type,
            'source_id' => $task->source_id,
            'file_path' => $task->file_path,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function paginate(int $pageSize, array $filters = []): LengthAwarePaginator
    {
        return ExportLog::query()
            ->when(isset($filters['user_id']), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate($pageSize);
    }
}
