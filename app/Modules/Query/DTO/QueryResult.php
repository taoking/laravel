<?php

namespace App\Modules\Query\DTO;

class QueryResult
{
    /**
     * @param  list<array{name: string, label: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $summary
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?int $total,
        public readonly ?array $summary,
        public readonly array $metadata,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'rows' => $this->rows,
            'total' => $this->total,
            'summary' => $this->summary,
            'metadata' => $this->metadata,
        ];
    }
}
