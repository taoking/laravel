<?php

namespace App\Modules\DataSource\Services;

use App\Modules\Cache\Services\CacheKeyBuilder;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DataSourceMetadataService
{
    public function __construct(
        private readonly DataSourceConnectionFactory $connectionFactory,
        private readonly DataSourceDriverManager $driverManager,
        private readonly CacheKeyBuilder $keyBuilder,
    ) {}

    /**
     * @return array{success: bool, message: string, elapsed_ms: int}
     */
    public function test(DataSource $dataSource): array
    {
        $startedAt = microtime(true);

        try {
            $connection = $this->connectionFactory->make($dataSource);
            $this->driverManager->driver($dataSource)->test($connection);

            $result = [
                'success' => true,
                'message' => 'Connection successful.',
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        } catch (Throwable $exception) {
            $result = [
                'success' => false,
                'message' => $exception->getMessage(),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        } finally {
            $this->connectionFactory->disconnect($dataSource);
        }

        $dataSource->forceFill([
            'last_tested_at' => now(),
            'last_test_result' => $result,
        ])->save();

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function databases(DataSource $dataSource): array
    {
        return Cache::remember($this->databasesCacheKey($dataSource), now()->addMinutes(10), function () use ($dataSource): array {
            $connection = $this->connectionFactory->make($dataSource);

            try {
                return $this->driverManager->driver($dataSource)->databases($connection, $dataSource);
            } finally {
                $this->connectionFactory->disconnect($dataSource);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tables(DataSource $dataSource): array
    {
        return Cache::remember($this->tablesCacheKey($dataSource), now()->addMinutes(10), function () use ($dataSource): array {
            $connection = $this->connectionFactory->make($dataSource);

            try {
                return $this->driverManager->driver($dataSource)->tables($connection, $dataSource);
            } finally {
                $this->connectionFactory->disconnect($dataSource);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function views(DataSource $dataSource): array
    {
        return Cache::remember($this->viewsCacheKey($dataSource), now()->addMinutes(10), function () use ($dataSource): array {
            $connection = $this->connectionFactory->make($dataSource);

            try {
                return $this->driverManager->driver($dataSource)->views($connection, $dataSource);
            } finally {
                $this->connectionFactory->disconnect($dataSource);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(DataSource $dataSource, string $tableName): array
    {
        if (! IdentifierGuard::isSafe($tableName)) {
            throw ValidationException::withMessages([
                'table' => ['The table name is not allowed.'],
            ]);
        }

        return Cache::remember($this->fieldsCacheKey($dataSource, $tableName), now()->addMinutes(10), function () use ($dataSource, $tableName): array {
            $connection = $this->connectionFactory->make($dataSource);

            try {
                return $this->driverManager->driver($dataSource)->fields($connection, $dataSource, $tableName);
            } finally {
                $this->connectionFactory->disconnect($dataSource);
            }
        });
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, limit: int}
     */
    public function preview(DataSource $dataSource, string $tableName, int $limit = 100): array
    {
        if (! IdentifierGuard::isSafe($tableName)) {
            throw ValidationException::withMessages([
                'table' => ['The table name is not allowed.'],
            ]);
        }

        $connection = $this->connectionFactory->make($dataSource);

        try {
            return $this->driverManager->driver($dataSource)->preview($connection, $dataSource, $tableName, $limit);
        } finally {
            $this->connectionFactory->disconnect($dataSource);
        }
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    public function explain(DataSource $dataSource, string $sql, array $bindings = []): array
    {
        $connection = $this->connectionFactory->make($dataSource);

        try {
            return $this->driverManager->driver($dataSource)->explain($connection, $dataSource, $sql, $bindings);
        } finally {
            $this->connectionFactory->disconnect($dataSource);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializedViews(DataSource $dataSource): array
    {
        $connection = $this->connectionFactory->make($dataSource);

        try {
            return $this->driverManager->driver($dataSource)->materializedViews($connection, $dataSource);
        } finally {
            $this->connectionFactory->disconnect($dataSource);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function materializedView(DataSource $dataSource, string $name): ?array
    {
        if (! IdentifierGuard::isSafe($name)) {
            throw ValidationException::withMessages([
                'name' => ['The materialized view name is not allowed.'],
            ]);
        }

        $connection = $this->connectionFactory->make($dataSource);

        try {
            return $this->driverManager->driver($dataSource)->materializedView($connection, $dataSource, $name);
        } finally {
            $this->connectionFactory->disconnect($dataSource);
        }
    }

    /**
     * @return array{refreshed: bool, message: string}
     */
    public function refreshMaterializedView(DataSource $dataSource, string $name): array
    {
        if (! IdentifierGuard::isSafe($name)) {
            throw ValidationException::withMessages([
                'name' => ['The materialized view name is not allowed.'],
            ]);
        }

        $connection = $this->connectionFactory->make($dataSource);

        try {
            return $this->driverManager->driver($dataSource)->refreshMaterializedView($connection, $dataSource, $name);
        } finally {
            $this->connectionFactory->disconnect($dataSource);
        }
    }

    /**
     * @return array{tables: int, fields: int}
     */
    public function sync(DataSource $dataSource): array
    {
        $this->forget($dataSource);

        $tables = $this->tables($dataSource);
        $fieldCount = 0;

        DB::transaction(function () use ($dataSource, $tables, &$fieldCount): void {
            $seenTableNames = [];

            foreach ($tables as $table) {
                $seenTableNames[] = $table['table_name'];

                $tableModel = DataSourceTable::query()->updateOrCreate(
                    [
                        'data_source_id' => $dataSource->id,
                        'table_name' => $table['table_name'],
                    ],
                    [
                        'table_comment' => $table['table_comment'],
                        'table_type' => $table['table_type'],
                        'row_count_estimate' => $table['row_count_estimate'],
                        'synced_at' => now(),
                    ],
                );

                $fields = $this->fields($dataSource, $table['table_name']);
                $seenFieldNames = [];

                foreach ($fields as $field) {
                    $seenFieldNames[] = $field['field_name'];

                    DataSourceField::query()->updateOrCreate(
                        [
                            'data_source_id' => $dataSource->id,
                            'table_name' => $table['table_name'],
                            'field_name' => $field['field_name'],
                        ],
                        [
                            'table_id' => $tableModel->id,
                            'field_comment' => $field['field_comment'],
                            'data_type' => $field['data_type'],
                            'normalized_type' => $field['normalized_type'],
                            'is_nullable' => $field['is_nullable'],
                            'is_primary_key' => $field['is_primary_key'],
                            'default_value' => $field['default_value'],
                            'ordinal_position' => $field['ordinal_position'],
                        ],
                    );
                }

                $fieldCount += count($fields);

                $staleFieldsQuery = DataSourceField::query()
                    ->where('data_source_id', $dataSource->id)
                    ->where('table_name', $table['table_name']);

                if ($seenFieldNames !== []) {
                    $staleFieldsQuery->whereNotIn('field_name', $seenFieldNames);
                }

                $staleFieldsQuery->delete();
            }

            $staleTablesQuery = DataSourceTable::query()
                ->where('data_source_id', $dataSource->id);

            if ($seenTableNames !== []) {
                $staleTablesQuery->whereNotIn('table_name', $seenTableNames);
            }

            $staleTablesQuery->delete();
        });

        $this->forget($dataSource);

        return [
            'tables' => count($tables),
            'fields' => $fieldCount,
        ];
    }

    public function forget(DataSource $dataSource): void
    {
        Cache::forget($this->databasesCacheKey($dataSource));
        Cache::forget($this->tablesCacheKey($dataSource));
        Cache::forget($this->viewsCacheKey($dataSource));

        DataSourceTable::query()
            ->where('data_source_id', $dataSource->id)
            ->pluck('table_name')
            ->each(fn (string $tableName) => Cache::forget($this->fieldsCacheKey($dataSource, $tableName)));
    }

    private function tablesCacheKey(DataSource $dataSource): string
    {
        return $this->keyBuilder->dataSourceTables((int) $dataSource->id);
    }

    private function databasesCacheKey(DataSource $dataSource): string
    {
        return $this->keyBuilder->dataSourceDatabases((int) $dataSource->id);
    }

    private function viewsCacheKey(DataSource $dataSource): string
    {
        return $this->keyBuilder->dataSourceViews((int) $dataSource->id);
    }

    private function fieldsCacheKey(DataSource $dataSource, string $tableName): string
    {
        return $this->keyBuilder->dataSourceFields((int) $dataSource->id, $tableName);
    }
}
