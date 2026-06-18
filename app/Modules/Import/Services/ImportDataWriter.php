<?php

namespace App\Modules\Import\Services;

use App\Modules\Import\Models\ImportTask;
use App\Modules\Import\Models\ImportTaskLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportDataWriter
{
    /**
     * @param  list<array{original_name: string, field_name: string, data_type: string, normalized_type: string, ordinal_position: int}>  $schema
     * @param  list<array<string, mixed>>  $rows
     * @return array{total_rows: int, success_rows: int, failed_rows: int}
     */
    public function write(ImportTask $task, string $tableName, array $schema, array $rows): array
    {
        Schema::dropIfExists($tableName);
        Schema::create($tableName, function (Blueprint $table) use ($schema): void {
            foreach ($schema as $field) {
                $this->addColumn($table, $field['field_name'], $field['normalized_type']);
            }
        });

        $totalRows = count($rows);
        $successRows = 0;
        $failedRows = 0;

        $task->forceFill([
            'total_rows' => $totalRows,
            'success_rows' => 0,
            'failed_rows' => 0,
            'progress' => $totalRows === 0 ? 80 : 20,
        ])->save();

        foreach ($rows as $index => $row) {
            try {
                DB::table($tableName)->insert($this->castRow($schema, $row));
                $successRows++;
            } catch (Throwable $exception) {
                $failedRows++;

                ImportTaskLog::query()->create([
                    'import_task_id' => $task->id,
                    'row_number' => $index + 2,
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                    'raw_data_json' => $row,
                    'created_at' => now(),
                ]);
            }

            $this->updateProgress($task, $totalRows, $successRows, $failedRows);
        }

        return [
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'failed_rows' => $failedRows,
        ];
    }

    private function addColumn(Blueprint $table, string $fieldName, string $normalizedType): void
    {
        match ($normalizedType) {
            'integer' => $table->bigInteger($fieldName)->nullable(),
            'decimal' => $table->decimal($fieldName, 20, 6)->nullable(),
            'boolean' => $table->boolean($fieldName)->nullable(),
            'datetime' => $table->dateTime($fieldName)->nullable(),
            default => $table->text($fieldName)->nullable(),
        };
    }

    /**
     * @param  list<array{original_name: string, field_name: string, data_type: string, normalized_type: string, ordinal_position: int}>  $schema
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function castRow(array $schema, array $row): array
    {
        $casted = [];

        foreach ($schema as $field) {
            $casted[$field['field_name']] = $this->castValue(
                $row[$field['original_name']] ?? null,
                $field['normalized_type'],
            );
        }

        return $casted;
    }

    private function castValue(mixed $value, string $normalizedType): mixed
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);

        return match ($normalizedType) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => in_array(strtolower($value), ['true', '1', 'yes', 'y'], true),
            'datetime' => CarbonImmutable::parse($value)->toDateTimeString(),
            default => $value,
        };
    }

    private function updateProgress(ImportTask $task, int $totalRows, int $successRows, int $failedRows): void
    {
        $processedRows = $successRows + $failedRows;
        $progress = $totalRows > 0 ? 20 + (int) floor(($processedRows / $totalRows) * 60) : 80;

        $task->forceFill([
            'success_rows' => $successRows,
            'failed_rows' => $failedRows,
            'progress' => min(80, $progress),
        ])->save();
    }
}
