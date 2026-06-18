<?php

namespace App\Modules\Import\Services;

use App\Modules\Import\Models\ImportTask;
use App\Modules\Import\Models\ImportTaskLog;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportTaskProcessor
{
    public function __construct(
        private readonly CsvParser $csvParser,
        private readonly ExcelParser $excelParser,
        private readonly ImportSchemaInferService $schemaInferService,
        private readonly ImportDataWriter $dataWriter,
        private readonly CreateDatasetFromImportService $datasetService,
    ) {}

    public function process(int $taskId): void
    {
        $task = ImportTask::query()->findOrFail($taskId);

        $task->forceFill([
            'status' => 'processing',
            'progress' => 5,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        try {
            $parsed = $this->parse($task);
            $schema = $this->schemaInferService->infer($parsed['headers'], $parsed['rows']);
            $tableName = $this->tableName($task);

            $result = $this->dataWriter->write($task, $tableName, $schema, $parsed['rows']);
            $task->refresh()->forceFill($result)->save();

            $this->datasetService->create($task->refresh(), $tableName, $schema);

            ImportTaskLog::query()->create([
                'import_task_id' => $task->id,
                'row_number' => null,
                'status' => 'completed',
                'message' => 'Import completed.',
                'raw_data_json' => $result,
                'created_at' => now(),
            ]);

            $task->forceFill([
                'status' => 'completed',
                'progress' => 100,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            ImportTaskLog::query()->create([
                'import_task_id' => $task->id,
                'row_number' => null,
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'raw_data_json' => null,
                'created_at' => now(),
            ]);

            $task->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }
    }

    /**
     * @return array{headers: list<string>, rows: list<array<string, mixed>>}
     */
    private function parse(ImportTask $task): array
    {
        $disk = (string) config('filesystems.import_disk', 'minio');
        $stream = Storage::disk($disk)->readStream($task->file_path);

        if ($stream === false) {
            throw new RuntimeException('The import file cannot be read.');
        }

        try {
            return match ($task->file_type) {
                'csv', 'txt' => $this->csvParser->parse($stream),
                'xlsx' => $this->excelParser->parse($stream),
                default => throw new RuntimeException("Unsupported import file type [{$task->file_type}]."),
            };
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function tableName(ImportTask $task): string
    {
        return 'uploaded_import_'.$task->id;
    }
}
