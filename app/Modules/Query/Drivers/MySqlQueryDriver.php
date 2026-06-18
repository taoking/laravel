<?php

namespace App\Modules\Query\Drivers;

use App\Modules\Query\DTO\CompiledQuery;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

class MySqlQueryDriver implements DatabaseDriverInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(ConnectionInterface $connection, CompiledQuery $query): array
    {
        $this->assertSafeSelect($query->sql);

        return collect($connection->select($query->sql, $query->bindings))
            ->map(fn (object|array $row): array => (array) $row)
            ->values()
            ->all();
    }

    private function assertSafeSelect(string $sql): void
    {
        if (preg_match('/\A\s*select\b/i', $sql) !== 1) {
            throw new InvalidArgumentException('Only SELECT queries are allowed.');
        }

        if (preg_match('/\b(drop|delete|update|insert|alter|truncate)\b/i', $sql) === 1) {
            throw new InvalidArgumentException('The query contains a forbidden SQL keyword.');
        }
    }
}
