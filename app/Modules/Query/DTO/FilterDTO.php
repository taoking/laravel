<?php

namespace App\Modules\Query\DTO;

class FilterDTO
{
    public function __construct(
        public readonly string $field,
        public readonly string $operator,
        public readonly mixed $value = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            field: (string) $payload['field'],
            operator: (string) $payload['operator'],
            value: $payload['value'] ?? null,
        );
    }
}
