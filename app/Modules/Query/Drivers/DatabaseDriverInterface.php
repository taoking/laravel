<?php

namespace App\Modules\Query\Drivers;

use App\Modules\Query\DTO\CompiledQuery;
use Illuminate\Database\ConnectionInterface;

interface DatabaseDriverInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(ConnectionInterface $connection, CompiledQuery $query): array;
}
