<?php

namespace App\Modules\Query\DTO;

class QueryExecutionResult
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $elapsedMs,
    ) {}
}
