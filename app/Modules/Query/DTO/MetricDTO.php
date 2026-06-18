<?php

namespace App\Modules\Query\DTO;

class MetricDTO
{
    public function __construct(
        public readonly string $field,
        public readonly ?string $aggregate = null,
        public readonly ?string $alias = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            field: (string) $payload['field'],
            aggregate: isset($payload['aggregate']) ? (string) $payload['aggregate'] : null,
            alias: isset($payload['alias']) ? (string) $payload['alias'] : null,
        );
    }
}
