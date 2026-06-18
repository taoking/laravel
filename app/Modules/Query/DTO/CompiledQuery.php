<?php

namespace App\Modules\Query\DTO;

class CompiledQuery
{
    /**
     * @param  list<mixed>  $bindings
     * @param  list<array{name: string, label: string, type: string}>  $columns
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings,
        public readonly array $columns,
        public readonly string $hash,
    ) {}
}
