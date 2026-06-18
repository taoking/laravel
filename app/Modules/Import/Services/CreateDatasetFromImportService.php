<?php

namespace App\Modules\Import\Services;

use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Dataset\Models\DatasetTable;
use App\Modules\Dataset\Services\DatasetFieldClassifier;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Import\Models\ImportTask;
use App\Modules\Import\Models\UploadedTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDatasetFromImportService
{
    public function __construct(
        private readonly DataSourcePasswordEncryptor $passwordEncryptor,
        private readonly DatasetFieldClassifier $fieldClassifier,
    ) {}

    /**
     * @param  list<array{original_name: string, field_name: string, data_type: string, normalized_type: string, ordinal_position: int}>  $schema
     */
    public function create(ImportTask $task, string $tableName, array $schema): Dataset
    {
        return DB::transaction(function () use ($task, $tableName, $schema): Dataset {
            $dataSource = $this->createDataSource($task);
            $sourceTable = $this->createSourceTable($dataSource, $tableName, $task);
            $dataset = $this->createDataset($task, $dataSource, $tableName);

            DatasetTable::query()->create([
                'dataset_id' => $dataset->id,
                'data_source_id' => $dataSource->id,
                'table_name' => $tableName,
                'alias' => $tableName,
                'sort_order' => 0,
            ]);

            foreach ($schema as $field) {
                $classification = $this->fieldClassifier->classify($field['field_name'], $field['normalized_type']);

                DataSourceField::query()->create([
                    'data_source_id' => $dataSource->id,
                    'table_id' => $sourceTable->id,
                    'table_name' => $tableName,
                    'field_name' => $field['field_name'],
                    'field_comment' => $field['original_name'],
                    'data_type' => $field['data_type'],
                    'normalized_type' => $field['normalized_type'],
                    'is_nullable' => true,
                    'is_primary_key' => false,
                    'default_value' => null,
                    'ordinal_position' => $field['ordinal_position'],
                ]);

                DatasetField::query()->create([
                    'dataset_id' => $dataset->id,
                    'table_name' => $tableName,
                    'field_name' => $field['field_name'],
                    'field_alias' => $field['field_name'],
                    'display_name' => $field['original_name'],
                    'source_type' => 'physical',
                    'normalized_type' => $field['normalized_type'],
                    'semantic_type' => $classification['semantic_type'],
                    'is_dimension' => $classification['is_dimension'],
                    'is_metric' => $classification['is_metric'],
                    'is_visible' => true,
                    'is_filterable' => true,
                    'default_aggregate' => $classification['default_aggregate'],
                    'expression' => null,
                    'sort_order' => $field['ordinal_position'],
                ]);
            }

            UploadedTable::query()->updateOrCreate(
                ['import_task_id' => $task->id],
                [
                    'tenant_id' => $task->tenant_id,
                    'table_name' => $tableName,
                    'display_name' => $this->displayName($task),
                    'schema_json' => $schema,
                ],
            );

            return $dataset->load(['dataSource', 'tables', 'fields']);
        });
    }

    private function createDataSource(ImportTask $task): DataSource
    {
        $mysql = (array) config('database.connections.mysql', []);

        return DataSource::query()->create([
            'tenant_id' => $task->tenant_id,
            'name' => 'Imported: '.$this->displayName($task),
            'type' => 'mysql',
            'host' => (string) ($mysql['host'] ?? 'mysql'),
            'port' => (int) ($mysql['port'] ?? 3306),
            'database_name' => (string) ($mysql['database'] ?? config('database.connections.'.config('database.default').'.database', 'laravel')),
            'username' => (string) ($mysql['username'] ?? 'root'),
            'password_encrypted' => $this->passwordEncryptor->encrypt((string) ($mysql['password'] ?? '')),
            'charset' => (string) ($mysql['charset'] ?? 'utf8mb4'),
            'timezone' => '+00:00',
            'options_json' => [
                'source' => 'import_task',
                'import_task_id' => $task->id,
                'app_connection' => config('database.default'),
            ],
            'status' => 'active',
            'created_by' => $task->created_by,
            'updated_by' => $task->created_by,
        ]);
    }

    private function createSourceTable(DataSource $dataSource, string $tableName, ImportTask $task): DataSourceTable
    {
        return DataSourceTable::query()->create([
            'data_source_id' => $dataSource->id,
            'table_name' => $tableName,
            'table_comment' => 'Imported from '.$task->file_name,
            'table_type' => 'BASE TABLE',
            'row_count_estimate' => $task->success_rows,
            'synced_at' => now(),
        ]);
    }

    private function createDataset(ImportTask $task, DataSource $dataSource, string $tableName): Dataset
    {
        return Dataset::query()->create([
            'tenant_id' => $task->tenant_id,
            'name' => $this->displayName($task),
            'description' => 'Generated from import task #'.$task->id,
            'data_source_id' => $dataSource->id,
            'dataset_type' => 'single_table',
            'main_table' => $tableName,
            'config_json' => [
                'source' => 'import_task',
                'import_task_id' => $task->id,
            ],
            'status' => 'active',
            'created_by' => $task->created_by,
            'updated_by' => $task->created_by,
        ]);
    }

    private function displayName(ImportTask $task): string
    {
        $basename = pathinfo($task->file_name, PATHINFO_FILENAME);
        $basename = trim((string) $basename);

        return $basename !== '' ? Str::limit($basename, 120, '') : 'Imported Dataset '.$task->id;
    }
}
