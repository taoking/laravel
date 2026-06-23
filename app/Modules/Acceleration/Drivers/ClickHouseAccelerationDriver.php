<?php

namespace App\Modules\Acceleration\Drivers;

use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationColumn;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Services\ClickHouseClient;
use App\Modules\Acceleration\Services\ClickHouseSqlGenerator;
use App\Modules\DataSource\Services\IdentifierGuard;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryExecutionResult;
use InvalidArgumentException;

class ClickHouseAccelerationDriver implements AccelerationDriverInterface
{
    public function __construct(
        private readonly ClickHouseClient $client,
        private readonly ClickHouseSqlGenerator $sqlGenerator,
    ) {}

    public function testConnection(): bool
    {
        $this->client->select('select 1 as ok');

        return true;
    }

    public function createDatabaseIfNotExists(string $database): void
    {
        $this->assertIdentifier($database);
        $this->client->execute('CREATE DATABASE IF NOT EXISTS '.$this->quoteIdentifier($database));
    }

    public function tableExists(AccelerationProfile $profile): bool
    {
        $database = $this->database($profile);

        try {
            $rows = $this->client->select('EXISTS TABLE '.$this->qualifiedTable($profile), $database);
        } catch (\Throwable) {
            return false;
        }

        $first = $rows[0] ?? [];
        $value = $first['result'] ?? $first['exists'] ?? reset($first);

        return (int) $value === 1;
    }

    public function createDetailTable(AccelerationProfile $profile): void
    {
        $profile->loadMissing('columns');
        $database = $this->database($profile);
        $this->createDatabaseIfNotExists($database);

        $definitions = $profile->columns
            ->map(fn (AccelerationColumn $column): string => $this->quoteIdentifier($column->target_field_name).' '.$column->target_type)
            ->implode(",\n  ");

        if ($definitions === '') {
            throw new InvalidArgumentException('Cannot create an acceleration table without columns.');
        }

        $partition = $this->partitionExpression($profile);
        $order = $this->orderExpression($profile);
        $sql = "CREATE TABLE IF NOT EXISTS {$this->qualifiedTable($profile)} (\n  {$definitions}\n) ENGINE = MergeTree";

        if ($partition !== null) {
            $sql .= "\nPARTITION BY {$partition}";
        }

        $sql .= "\nORDER BY {$order}";

        $this->client->execute($sql, $database);
    }

    public function dropTable(AccelerationProfile $profile): void
    {
        $this->client->execute('DROP TABLE IF EXISTS '.$this->qualifiedTable($profile), $this->database($profile));
    }

    public function insertRows(AccelerationProfile $profile, iterable $rows): int
    {
        $profile->loadMissing('columns');
        $mappedRows = [];

        foreach ($rows as $row) {
            $sourceRow = (array) $row;
            $targetRow = [];

            foreach ($profile->columns as $column) {
                $targetRow[$column->target_field_name] = $sourceRow[$column->source_field_name] ?? null;
            }

            $mappedRows[] = $targetRow;
        }

        return $this->client->insertJsonEachRow($this->database($profile), $profile->target_table, $mappedRows);
    }

    public function generateQuerySql(LogicalQueryPlan $plan, AccelerationProfile $profile): CompiledQuery
    {
        return $this->sqlGenerator->generate($plan, $profile);
    }

    public function executeQuery(AccelerationProfile $profile, CompiledQuery $query): QueryExecutionResult
    {
        $startedAt = microtime(true);
        $rows = $this->client->select($query->sql, $this->database($profile));

        return new QueryExecutionResult(
            rows: $rows,
            elapsedMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    public function supports(LogicalQueryPlan $plan, AccelerationProfile $profile): bool
    {
        return $profile->engine_type === 'clickhouse'
            && $profile->mode === 'detail_table'
            && $profile->status === 'active';
    }

    private function partitionExpression(AccelerationProfile $profile): ?string
    {
        $partitionColumn = $profile->columns->firstWhere('is_partition_key', true);

        if (! $partitionColumn instanceof AccelerationColumn) {
            return null;
        }

        $quoted = $this->quoteIdentifier($partitionColumn->target_field_name);

        return str_contains($partitionColumn->target_type, 'Date') ? "toYYYYMM({$quoted})" : $quoted;
    }

    private function orderExpression(AccelerationProfile $profile): string
    {
        $columns = $profile->columns
            ->where('is_order_key', true)
            ->map(fn (AccelerationColumn $column): string => $this->quoteIdentifier($column->target_field_name))
            ->values();

        return $columns->isEmpty() ? 'tuple()' : '('.$columns->implode(', ').')';
    }

    private function qualifiedTable(AccelerationProfile $profile): string
    {
        return $this->quoteIdentifier($this->database($profile)).'.'.$this->quoteIdentifier($profile->target_table);
    }

    private function database(AccelerationProfile $profile): string
    {
        return $profile->target_database ?: (string) config('bi_acceleration.clickhouse.database');
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->assertIdentifier($identifier);

        return '`'.$identifier.'`';
    }

    private function assertIdentifier(string $identifier): void
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw new InvalidArgumentException("Unsafe ClickHouse identifier [{$identifier}].");
        }
    }
}
