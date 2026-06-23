<?php

namespace App\Modules\DataSource\Drivers;

use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

class MySqlMetadataDriver implements DatabaseDriverInterface
{
    public function test(ConnectionInterface $connection): void
    {
        $connection->selectOne('select 1 as connection_test');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function databases(ConnectionInterface $connection, DataSource $dataSource): array
    {
        return collect($connection->select('show databases'))
            ->map(fn (object|array $row): ?array => $this->databaseRow($row))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tables(ConnectionInterface $connection, DataSource $dataSource): array
    {
        $rows = $connection->select(
            <<<'SQL'
select
    TABLE_NAME as table_name,
    TABLE_COMMENT as table_comment,
    TABLE_TYPE as table_type,
    TABLE_ROWS as row_count_estimate
from information_schema.TABLES
where TABLE_SCHEMA = ?
order by TABLE_NAME asc
SQL,
            [$dataSource->database_name],
        );

        return collect($rows)
            ->map(fn (object $row): array => [
                'table_name' => (string) $row->table_name,
                'table_comment' => $row->table_comment !== null ? (string) $row->table_comment : null,
                'table_type' => $row->table_type !== null ? (string) $row->table_type : null,
                'row_count_estimate' => $row->row_count_estimate !== null ? (int) $row->row_count_estimate : null,
            ])
            ->filter(fn (array $table): bool => IdentifierGuard::isSafe($table['table_name']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function views(ConnectionInterface $connection, DataSource $dataSource): array
    {
        return collect($this->tables($connection, $dataSource))
            ->filter(fn (array $table): bool => strtoupper((string) ($table['table_type'] ?? '')) === 'VIEW')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(ConnectionInterface $connection, DataSource $dataSource, string $tableName): array
    {
        if (! IdentifierGuard::isSafe($tableName)) {
            throw new InvalidArgumentException('Unsafe table name.');
        }

        $rows = $connection->select(
            <<<'SQL'
select
    COLUMN_NAME as field_name,
    COLUMN_COMMENT as field_comment,
    COLUMN_TYPE as data_type,
    DATA_TYPE as base_data_type,
    IS_NULLABLE as is_nullable,
    COLUMN_KEY as column_key,
    COLUMN_DEFAULT as default_value,
    ORDINAL_POSITION as ordinal_position
from information_schema.COLUMNS
where TABLE_SCHEMA = ?
  and TABLE_NAME = ?
order by ORDINAL_POSITION asc
SQL,
            [$dataSource->database_name, $tableName],
        );

        return collect($rows)
            ->map(fn (object $row): array => [
                'table_name' => $tableName,
                'field_name' => (string) $row->field_name,
                'field_comment' => $row->field_comment !== null ? (string) $row->field_comment : null,
                'data_type' => (string) $row->data_type,
                'normalized_type' => $this->normalizeType((string) $row->base_data_type),
                'is_nullable' => $row->is_nullable === 'YES',
                'is_primary_key' => $row->column_key === 'PRI',
                'default_value' => $row->default_value !== null ? (string) $row->default_value : null,
                'ordinal_position' => (int) $row->ordinal_position,
            ])
            ->filter(fn (array $field): bool => IdentifierGuard::isSafe($field['field_name']))
            ->values()
            ->all();
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, limit: int}
     */
    public function preview(ConnectionInterface $connection, DataSource $dataSource, string $tableName, int $limit = 100): array
    {
        if (! IdentifierGuard::isSafe($tableName)) {
            throw new InvalidArgumentException('Unsafe table name.');
        }

        $limit = min(max($limit, 1), 1000);
        $fields = $this->fields($connection, $dataSource, $tableName);
        $columns = collect($fields)
            ->pluck('field_name')
            ->take(50)
            ->values()
            ->all();

        if ($columns === []) {
            return [
                'columns' => [],
                'rows' => [],
                'limit' => $limit,
            ];
        }

        $selectSql = collect($columns)
            ->map(fn (string $field): string => $this->quoteIdentifier($field))
            ->implode(', ');
        $sql = sprintf('select %s from %s limit %d', $selectSql, $this->quoteIdentifier($tableName), $limit);

        return [
            'columns' => $columns,
            'rows' => collect($connection->select($sql))
                ->map(fn (object|array $row): array => (array) $row)
                ->values()
                ->all(),
            'limit' => $limit,
        ];
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    public function explain(ConnectionInterface $connection, DataSource $dataSource, string $sql, array $bindings = []): array
    {
        $this->assertSafeSelect($sql);

        return collect($connection->select('explain '.$sql, $bindings))
            ->map(fn (object|array $row): array => (array) $row)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materializedViews(ConnectionInterface $connection, DataSource $dataSource): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function materializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): ?array
    {
        if (! IdentifierGuard::isSafe($name)) {
            throw new InvalidArgumentException('Unsafe materialized view name.');
        }

        return collect($this->materializedViews($connection, $dataSource))
            ->first(fn (array $view): bool => ($view['name'] ?? null) === $name);
    }

    /**
     * @return array{refreshed: bool, message: string}
     */
    public function refreshMaterializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): array
    {
        if (! IdentifierGuard::isSafe($name)) {
            throw new InvalidArgumentException('Unsafe materialized view name.');
        }

        return [
            'refreshed' => false,
            'message' => 'Materialized view refresh is not supported by this data source driver.',
        ];
    }

    public function dialect(): string
    {
        return 'mysql';
    }

    protected function normalizeType(string $dataType): string
    {
        return match (strtolower($dataType)) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => 'integer',
            'decimal', 'numeric' => 'decimal',
            'float', 'double', 'real' => 'float',
            'date' => 'date',
            'datetime', 'timestamp', 'time', 'year' => 'datetime',
            'json' => 'json',
            'bit', 'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }

    protected function quoteIdentifier(string $identifier): string
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw new InvalidArgumentException("Unsafe SQL identifier [{$identifier}].");
        }

        return '`'.$identifier.'`';
    }

    private function databaseRow(object|array $row): ?array
    {
        $values = array_values((array) $row);
        $database = isset($values[0]) ? (string) $values[0] : null;

        if ($database === null || ! IdentifierGuard::isSafe($database)) {
            return null;
        }

        return [
            'database_name' => $database,
            'name' => $database,
        ];
    }

    private function assertSafeSelect(string $sql): void
    {
        if (preg_match('/\A\s*select\b/i', $sql) !== 1) {
            throw new InvalidArgumentException('Only SELECT queries can be explained.');
        }

        if (preg_match('/\b(drop|delete|update|insert|alter|truncate)\b/i', $sql) === 1) {
            throw new InvalidArgumentException('The query contains a forbidden SQL keyword.');
        }
    }
}
