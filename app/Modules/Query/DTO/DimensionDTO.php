<?php

namespace App\Modules\Query\DTO;

class DimensionDTO
{
    public function __construct(
        public readonly string $field,
        public readonly ?string $timeGranularity = null,
        public readonly ?string $alias = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            field: (string) $payload['field'],
            timeGranularity: isset($payload['time_granularity']) ? (string) $payload['time_granularity'] : null,
            alias: isset($payload['alias']) ? (string) $payload['alias'] : null,
        );
    }
}
