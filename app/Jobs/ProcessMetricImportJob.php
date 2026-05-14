<?php

namespace App\Jobs;

use App\Domains\Imports\Models\ImportFailure;
use App\Domains\Imports\Models\ImportTask;
use App\Domains\Messaging\KafkaProducer;
use App\Domains\Metrics\Models\Frequency;
use App\Domains\Metrics\Models\Metric;
use App\Domains\Metrics\Models\MetricValue;
use App\Domains\Metrics\Models\Region;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Throwable;

class ProcessMetricImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $importTaskId) {}

    public function handle(KafkaProducer $producer): void
    {
        $task = ImportTask::query()->findOrFail($this->importTaskId);

        if (in_array($task->status, ['processing', 'completed'], true)) {
            return;
        }

        $task->forceFill([
            'status' => 'processing',
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ])->save();

        try {
            $this->process($task);
            $this->publishCompletedEvent($producer, $task->refresh());
        } catch (Throwable $exception) {
            $task->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    private function process(ImportTask $task): void
    {
        $path = Storage::disk($task->disk)->path($task->path);
        $headers = [];
        $total = 0;
        $success = 0;
        $failed = 0;

        foreach ($this->csvRows($path) as $index => $row) {
            if ($index === 0) {
                $headers = $this->normalizeHeaders($row);

                continue;
            }

            $total++;
            $rowNumber = $index + 1;
            $payload = array_combine($headers, array_pad($row, count($headers), null)) ?: [];
            $errors = $this->validatePayload($payload);

            if ($errors !== []) {
                $this->recordFailure($task, $rowNumber, $payload, $errors);
                $failed++;

                continue;
            }

            $metric = Metric::query()->where('code', $payload['metric_code'])->first();
            $region = Region::query()->where('code', $payload['region_code'])->first();
            $frequency = Frequency::query()->where('code', $payload['frequency_code'])->first();

            if (! $metric || ! $region || ! $frequency) {
                $this->recordFailure($task, $rowNumber, $payload, [
                    'dimension' => ['Metric, region, or frequency code does not exist.'],
                ]);
                $failed++;

                continue;
            }

            $periodDate = Carbon::parse($payload['period_date'])->toDateString();
            $value = MetricValue::query()
                ->where([
                    'metric_id' => $metric->id,
                    'region_id' => $region->id,
                    'frequency_id' => $frequency->id,
                ])
                ->whereDate('period_date', $periodDate)
                ->first();

            $attributes = [
                'period_label' => $payload['period_label'],
                'value' => $payload['value'],
                'source' => $payload['source'] ?? 'csv',
            ];

            if ($value) {
                $value->fill($attributes)->save();
            } else {
                MetricValue::query()->create([
                    'metric_id' => $metric->id,
                    'region_id' => $region->id,
                    'frequency_id' => $frequency->id,
                    'period_date' => $periodDate,
                    'period_label' => $payload['period_label'],
                    'value' => $payload['value'],
                    'source' => $payload['source'] ?? 'csv',
                ]);
            }

            $success++;
        }

        $task->forceFill([
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'total_rows' => $total,
            'success_rows' => $success,
            'failed_rows' => $failed,
            'finished_at' => now(),
        ])->save();
    }

    private function csvRows(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $handle = fopen($path, 'rb');

            try {
                while (($row = fgetcsv($handle)) !== false) {
                    yield $row;
                }
            } finally {
                fclose($handle);
            }
        });
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn ($header) => trim((string) $header), $headers);
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        foreach (['metric_code', 'region_code', 'frequency_code', 'period_date', 'period_label', 'value'] as $field) {
            if (($payload[$field] ?? '') === '') {
                $errors[$field][] = 'The field is required.';
            }
        }

        if (isset($payload['value']) && $payload['value'] !== '' && ! is_numeric($payload['value'])) {
            $errors['value'][] = 'The value must be numeric.';
        }

        return $errors;
    }

    private function recordFailure(ImportTask $task, int $rowNumber, array $payload, array $errors): void
    {
        ImportFailure::query()->create([
            'import_task_id' => $task->id,
            'row_number' => $rowNumber,
            'payload' => $payload,
            'errors' => $errors,
        ]);
    }

    private function publishCompletedEvent(KafkaProducer $producer, ImportTask $task): void
    {
        $producer->publishEvent('metric.import.completed', [
            'import_task_id' => $task->id,
            'status' => $task->status,
            'total_rows' => $task->total_rows,
            'success_rows' => $task->success_rows,
            'failed_rows' => $task->failed_rows,
            'started_at' => $task->started_at?->toIso8601String(),
            'finished_at' => $task->finished_at?->toIso8601String(),
            'operator_id' => $task->user_id,
        ]);
    }
}
