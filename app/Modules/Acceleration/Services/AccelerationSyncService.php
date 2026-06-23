<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Models\AccelerationTask;
use App\Modules\Cache\Services\ChartCacheService;
use App\Modules\Chart\Models\Chart;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\IdentifierGuard;
use RuntimeException;
use Throwable;

class AccelerationSyncService
{
    public function __construct(
        private readonly AccelerationDriverManager $driverManager,
        private readonly DataSourceConnectionFactory $connectionFactory,
        private readonly ChartCacheService $chartCacheService,
    ) {}

    public function process(int $taskId): void
    {
        $task = AccelerationTask::query()
            ->with('profile.dataset.dataSource', 'profile.columns')
            ->findOrFail($taskId);
        $profile = $task->profile;
        $dataset = $profile->dataset;
        $startedAt = microtime(true);

        $task->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        $profile->forceFill([
            'status' => 'building',
            'last_refresh_at' => now(),
            'last_error_message' => null,
        ])->save();

        try {
            if (! IdentifierGuard::isSafe($dataset->main_table)) {
                throw new RuntimeException("Unsafe source table [{$dataset->main_table}].");
            }

            $driver = $this->driverManager->driver($profile);
            $driver->dropTable($profile);
            $driver->createDetailTable($profile);
            $sourceRows = 0;
            $targetRows = 0;
            $chunkSize = (int) config('bi_acceleration.sync.chunk_size', 5000);
            $columns = $profile->columns->pluck('source_field_name')->values()->all();

            foreach ($columns as $column) {
                if (! IdentifierGuard::isSafe((string) $column)) {
                    throw new RuntimeException("Unsafe source column [{$column}].");
                }
            }

            $connection = $this->connectionFactory->make($dataset->dataSource);

            try {
                for ($offset = 0; ; $offset += $chunkSize) {
                    $rows = $connection->table($dataset->main_table)
                        ->select($columns)
                        ->limit($chunkSize)
                        ->offset($offset)
                        ->get()
                        ->map(fn (object|array $row): array => (array) $row)
                        ->all();

                    if ($rows === []) {
                        break;
                    }

                    $sourceRows += count($rows);
                    $targetRows += $driver->insertRows($profile, $rows);
                }
            } finally {
                $this->connectionFactory->disconnect($dataset->dataSource);
            }

            $profile->forceFill([
                'status' => 'active',
                'row_count' => $targetRows,
                'version' => $profile->version + 1,
                'last_success_at' => now(),
                'last_error_message' => null,
            ])->save();

            $task->forceFill([
                'status' => 'success',
                'finished_at' => now(),
                'source_row_count' => $sourceRows,
                'target_row_count' => $targetRows,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ])->save();

            $this->forgetDatasetChartCaches((int) $dataset->id);
        } catch (Throwable $exception) {
            $profile->forceFill([
                'status' => 'failed',
                'last_error_message' => $exception->getMessage(),
            ])->save();

            $task->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function forgetDatasetChartCaches(int $datasetId): void
    {
        Chart::query()
            ->where('dataset_id', $datasetId)
            ->pluck('id')
            ->each(fn (int $chartId): mixed => $this->chartCacheService->forget($chartId));
    }
}
