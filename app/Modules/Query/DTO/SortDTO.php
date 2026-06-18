<?php

namespace App\Modules\Query\DTO;

class SortDTO
{
    public function __construct(
        public readonly string $field,
        public readonly string $direction = 'asc',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            field: (string) $payload['field'],
            direction: strtolower((string) ($payload['direction'] ?? 'asc')),
        );
    }
}
