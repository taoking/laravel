<?php

namespace App\Modules\Export\Services;

use App\Modules\Audit\Services\ExportLogService;
use App\Modules\Export\Models\ExportTask;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ExportTaskProcessor
{
    public function __construct(
        private readonly ChartExportService $chartExportService,
        private readonly DashboardExportService $dashboardExportService,
        private readonly ExportLogService $exportLogService,
    ) {}

    public function process(int $taskId): void
    {
        $task = ExportTask::query()->with('creator')->findOrFail($taskId);

        $task->forceFill([
            'status' => 'processing',
            'progress' => 10,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        try {
            $result = match ($task->source_type) {
                'chart' => $this->chartExportService->export($task, $task->creator),
                'dashboard' => $this->dashboardExportService->export($task, $task->creator),
                default => throw new RuntimeException("Unsupported export source type [{$task->source_type}]."),
            };

            $path = $this->store($task, $result['file_name'], $result['content']);

            $task->forceFill([
                'status' => 'completed',
                'file_name' => $result['file_name'],
                'file_path' => $path,
                'file_size' => strlen($result['content']),
                'progress' => 100,
                'finished_at' => now(),
            ])->save();
            $this->exportLogService->record($task->refresh(), 'completed');
        } catch (Throwable $exception) {
            $task->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
            $this->exportLogService->record($task->refresh(), 'failed');
        }
    }

    private function store(ExportTask $task, string $fileName, string $content): string
    {
        $disk = (string) config('filesystems.export_disk', 'minio');
        $path = 'exports/'.now()->format('Y/m/d').'/'.$task->id.'_'.$fileName;

        if (! Storage::disk($disk)->put($path, $content)) {
            throw new RuntimeException('Failed to store export file.');
        }

        return $path;
    }
}
