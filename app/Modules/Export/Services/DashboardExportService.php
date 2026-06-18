<?php

namespace App\Modules\Export\Services;

use App\Models\User;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Services\DashboardDataService;
use App\Modules\Export\Models\ExportTask;
use Illuminate\Support\Str;
use RuntimeException;

class DashboardExportService
{
    public function __construct(
        private readonly DashboardDataService $dashboardDataService,
        private readonly PdfExportService $pdfExportService,
    ) {}

    /**
     * @return array{content: string, file_name: string, mime_type: string}
     */
    public function export(ExportTask $task, ?User $actor): array
    {
        if ($task->export_type !== 'pdf') {
            throw new RuntimeException("Unsupported dashboard export type [{$task->export_type}].");
        }

        $dashboard = Dashboard::query()->findOrFail($task->source_id);
        $data = $this->dashboardDataService->data($dashboard, [], $actor);

        return [
            'content' => $this->pdfExportService->build($dashboard->name, $this->lines($data)),
            'file_name' => $this->fileName($dashboard->name),
            'mime_type' => 'application/pdf',
        ];
    }

    /**
     * @param  array{dashboard_id: int, filters: list<array<string, mixed>>, widgets: list<array<string, mixed>>}  $data
     * @return list<string>
     */
    private function lines(array $data): array
    {
        $lines = [
            'Dashboard ID: '.$data['dashboard_id'],
            'Filters: '.count($data['filters']),
            'Widgets: '.count($data['widgets']),
        ];

        foreach ($data['widgets'] as $widget) {
            $lines[] = 'Widget #'.$widget['widget_id'].' Chart #'.$widget['chart_id'].' Rows: '.($widget['data']['meta']['total'] ?? count($widget['data']['rows'] ?? []));

            foreach (array_slice($widget['data']['rows'] ?? [], 0, 3) as $row) {
                $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }
        }

        return $lines;
    }

    private function fileName(string $name): string
    {
        $base = Str::slug($name, '_');

        return ($base !== '' ? $base : 'dashboard_export').'.pdf';
    }
}
