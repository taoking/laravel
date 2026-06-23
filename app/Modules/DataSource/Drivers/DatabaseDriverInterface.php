<?php

namespace App\Modules\DataSource\Drivers;

use App\Modules\DataSource\Models\DataSource;
use Illuminate\Database\ConnectionInterface;

interface DatabaseDriverInterface
{
    public function test(ConnectionInterface $connection): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function databases(ConnectionInterface $connection, DataSource $dataSource): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function tables(ConnectionInterface $connection, DataSource $dataSource): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function views(ConnectionInterface $connection, DataSource $dataSource): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(ConnectionInterface $connection, DataSource $dataSource, string $tableName): array;

    /**
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, limit: int}
     */
    public function preview(ConnectionInterface $connection, DataSource $dataSource, string $tableName, int $limit = 100): array;

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    public function explain(ConnectionInterface $connection, DataSource $dataSource, string $sql, array $bindings = []): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function materializedViews(ConnectionInterface $connection, DataSource $dataSource): array;

    /**
     * @return array<string, mixed>|null
     */
    public function materializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): ?array;

    /**
     * @return array{refreshed: bool, message: string}
     */
    public function refreshMaterializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): array;

    public function dialect(): string;
}
