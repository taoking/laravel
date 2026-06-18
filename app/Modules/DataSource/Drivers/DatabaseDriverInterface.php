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
    public function tables(ConnectionInterface $connection, DataSource $dataSource): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(ConnectionInterface $connection, DataSource $dataSource, string $tableName): array;
}
