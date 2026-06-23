<?php

namespace App\Modules\DataSource\Drivers;

use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Throwable;

abstract class MySqlProtocolOlapMetadataDriver extends MySqlMetadataDriver
{
    /**
     * @return list<array<string, mixed>>
     */
    public function materializedViews(ConnectionInterface $connection, DataSource $dataSource): array
    {
        try {
            $rows = $connection->select(
                <<<'SQL'
select
    TABLE_SCHEMA as database_name,
    TABLE_NAME as name,
    TABLE_NAME as table_name,
    TABLE_TYPE as table_type,
    TABLE_COMMENT as comment
from information_schema.TABLES
where TABLE_SCHEMA = ?
  and (
      upper(TABLE_TYPE) like '%MATERIALIZED%'
      or upper(TABLE_COMMENT) like '%MATERIALIZED%'
  )
order by TABLE_NAME asc
SQL,
                [$dataSource->database_name],
            );
        } catch (Throwable) {
            return [];
        }

        return collect($rows)
            ->map(fn (object|array $row): array => $this->materializedViewRow((array) $row, $dataSource->database_name))
            ->filter(fn (array $view): bool => IdentifierGuard::isSafe((string) $view['name']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function materializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): ?array
    {
        if (! IdentifierGuard::isSafe($name)) {
            throw new InvalidArgumentException('Unsafe materialized view name.');
        }

        $view = parent::materializedView($connection, $dataSource, $name);

        if ($view === null) {
            return null;
        }

        return [
            ...$view,
            'definition_sql' => $this->showCreateTable($connection, $name),
        ];
    }

    /**
     * @return array{refreshed: bool, message: string}
     */
    public function refreshMaterializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): array
    {
        if (! IdentifierGuard::isSafe($name)) {
            throw new InvalidArgumentException('Unsafe materialized view name.');
        }

        $connection->statement('refresh materialized view '.$this->quoteIdentifier($name));

        return [
            'refreshed' => true,
            'message' => 'Materialized view refresh submitted.',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function materializedViewRow(array $row, string $defaultDatabase): array
    {
        return [
            'database_name' => (string) ($row['database_name'] ?? $defaultDatabase),
            'name' => (string) ($row['name'] ?? $row['table_name'] ?? ''),
            'table_name' => (string) ($row['table_name'] ?? $row['name'] ?? ''),
            'table_type' => (string) ($row['table_type'] ?? 'MATERIALIZED VIEW'),
            'status' => $row['status'] ?? null,
            'comment' => $row['comment'] ?? null,
            'last_refresh_at' => $row['last_refresh_at'] ?? null,
            'definition_sql' => $row['definition_sql'] ?? null,
        ];
    }

    private function showCreateTable(ConnectionInterface $connection, string $name): ?string
    {
        try {
            $rows = $connection->select('show create table '.$this->quoteIdentifier($name));
        } catch (Throwable) {
            return null;
        }

        $first = (array) ($rows[0] ?? []);

        foreach ($first as $key => $value) {
            if (str_contains(strtolower((string) $key), 'create')) {
                return $value !== null ? (string) $value : null;
            }
        }

        return null;
    }
}
