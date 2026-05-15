<?php

namespace App\Jobs;

use App\Domains\Imports\Models\ExportTask;
use App\Domains\Metrics\Models\Metric;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessMetricExportJob implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 500;

    private const PROGRESS_UPDATE_INTERVAL = 500;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $exportTaskId) {}

    public function handle(): void
    {
        $task = ExportTask::query()->findOrFail($this->exportTaskId);

        if (in_array($task->status, ['processing', 'completed'], true)) {
            return;
        }

        $attempts = max($task->attempts + 1, $this->attempts());

        $task->forceFill([
            'status' => 'processing',
            'attempts' => $attempts,
            'started_at' => now(),
            'finished_at' => null,
            'downloaded_at' => null,
            'error_message' => null,
            'failure_type' => null,
            'last_failed_at' => null,
            'processed_rows' => 0,
            'file_size' => 0,
        ])->save();

        try {
            $this->process($task);
        } catch (Throwable $exception) {
            $this->deletePartialFile($task->refresh());

            $task->forceFill([
                'status' => 'failed',
                'path' => null,
                'file_size' => 0,
                'error_message' => $exception->getMessage(),
                'failure_type' => $this->failureType($exception),
                'last_failed_at' => now(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    private function process(ExportTask $task): void
    {
        $path = sprintf('exports/metrics-%d-%s.csv', $task->id, now()->format('YmdHis'));
        $tempPath = tempnam(sys_get_temp_dir(), 'metrics-export-');

        if ($tempPath === false) {
            throw new RuntimeException('Unable to create temporary export file.');
        }

        $handle = @fopen($tempPath, 'wb');

        if (! is_resource($handle)) {
            @unlink($tempPath);

            throw new RuntimeException("Unable to open temporary export file [{$tempPath}].");
        }

        $total = 0;
        $processed = 0;

        try {
            $query = $this->metricQuery($task->filters ?? []);
            $total = (clone $query)->count();

            $task->forceFill([
                'path' => $path,
                'total_rows' => $total,
                'processed_rows' => 0,
            ])->save();

            $this->writeRow($handle, [
                'id',
                'code',
                'name',
                'unit',
                'status',
                'category_code',
                'category_name',
                'latest_value',
                'latest_period_date',
                'latest_period_label',
                'latest_region_code',
                'latest_frequency_code',
                'created_at',
            ]);

            $query->chunkById(self::CHUNK_SIZE, function (EloquentCollection $metrics) use ($handle, &$processed, $task): void {
                foreach ($metrics as $metric) {
                    $this->writeMetricRow($handle, $metric);
                    $processed++;
                }

                if ($processed % self::PROGRESS_UPDATE_INTERVAL === 0 || $processed === (int) $task->total_rows) {
                    $task->forceFill(['processed_rows' => $processed])->save();
                }
            });

            $task->forceFill(['processed_rows' => $processed])->save();
        } finally {
            fclose($handle);
        }

        $fileSize = (int) (filesize($tempPath) ?: 0);
        $stream = @fopen($tempPath, 'rb');

        if (! is_resource($stream)) {
            @unlink($tempPath);

            throw new RuntimeException("Unable to reopen temporary export file [{$tempPath}].");
        }

        try {
            Storage::disk($task->disk)->put($path, $stream);
        } finally {
            fclose($stream);
            @unlink($tempPath);
        }

        $task->forceFill([
            'status' => 'completed',
            'total_rows' => $total,
            'processed_rows' => $processed,
            'file_size' => $fileSize,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Metric>
     */
    private function metricQuery(array $filters): Builder
    {
        return Metric::query()
            ->with(['category', 'latestValue.region', 'latestValue.frequency'])
            ->when($filters['keyword'] ?? null, function (Builder $query, string $keyword): void {
                $query->where(function (Builder $query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $categoryId): Builder => $query->where('metric_category_id', $categoryId))
            ->when($filters['category_code'] ?? null, fn (Builder $query, string $code): Builder => $query->whereHas('category', fn (Builder $query): Builder => $query->where('code', $code)))
            ->when($filters['region_id'] ?? null, fn (Builder $query, int $regionId): Builder => $query->whereHas('values', fn (Builder $query): Builder => $query->where('region_id', $regionId)))
            ->when($filters['frequency_code'] ?? null, fn (Builder $query, string $code): Builder => $query->whereHas('values.frequency', fn (Builder $query): Builder => $query->where('code', $code)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereHas('values', fn (Builder $query): Builder => $query->whereDate('period_date', '>=', $date)))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereHas('values', fn (Builder $query): Builder => $query->whereDate('period_date', '<=', $date)));
    }

    /**
     * @param  resource  $handle
     */
    private function writeMetricRow(mixed $handle, Metric $metric): void
    {
        $latestValue = $metric->latestValue;

        $this->writeRow($handle, [
            $metric->id,
            $metric->code,
            $metric->name,
            $metric->unit,
            $metric->status,
            $metric->category?->code,
            $metric->category?->name,
            $latestValue?->value,
            $latestValue?->period_date?->toDateString(),
            $latestValue?->period_label,
            $latestValue?->region?->code,
            $latestValue?->frequency?->code,
            $metric->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $row
     */
    private function writeRow(mixed $handle, array $row): void
    {
        $written = fputcsv($handle, array_map(
            fn (mixed $value): string => $value === null ? '' : (string) $value,
            $row,
        ));

        if ($written === false) {
            throw new RuntimeException('Unable to write export CSV row.');
        }
    }

    private function deletePartialFile(ExportTask $task): void
    {
        if (! $task->path) {
            return;
        }

        try {
            $disk = Storage::disk($task->disk);

            if ($disk->exists($task->path)) {
                $disk->delete($task->path);
            }
        } catch (Throwable) {
            // The original exception is more useful to the caller; cleanup must not hide it.
        }
    }

    private function failureType(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'file') || str_contains($message, 'disk') || str_contains($message, 'storage')) {
            return 'storage';
        }

        if (str_contains($message, 'memory') || str_contains($message, 'timeout')) {
            return 'runtime';
        }

        return 'unexpected';
    }
}
