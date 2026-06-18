<?php

namespace App\Modules\Export\Services;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Chart\Services\ChartDataService;
use App\Modules\Export\Models\ExportTask;
use Illuminate\Support\Str;
use RuntimeException;

class ChartExportService
{
    public function __construct(
        private readonly ChartDataService $chartDataService,
        private readonly CsvExportService $csvExportService,
        private readonly ExcelExportService $excelExportService,
    ) {}

    /**
     * @return array{content: string, file_name: string, mime_type: string}
     */
    public function export(ExportTask $task, ?User $actor): array
    {
        $chart = Chart::query()->findOrFail($task->source_id);
        $data = $this->chartDataService->data($chart, ['use_cache' => false], $actor);

        $content = match ($task->export_type) {
            'csv' => $this->csvExportService->build($data['columns'], $data['rows']),
            'xlsx' => $this->excelExportService->build($data['columns'], $data['rows']),
            default => throw new RuntimeException("Unsupported chart export type [{$task->export_type}]."),
        };

        return [
            'content' => $content,
            'file_name' => $this->fileName($chart->name, $task->export_type),
            'mime_type' => $this->mimeType($task->export_type),
        ];
    }

    private function fileName(string $name, string $extension): string
    {
        $base = Str::slug($name, '_');

        return ($base !== '' ? $base : 'chart_export').'.'.$extension;
    }

    private function mimeType(string $exportType): string
    {
        return match ($exportType) {
            'csv' => 'text/csv; charset=UTF-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
