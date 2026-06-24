<?php

namespace App\Modules\Query\DTO;

class QueryExecutionMetadata
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly bool $cacheHit,
        public readonly bool $semanticLayerUsed,
        public readonly bool $permissionApplied,
        public readonly bool $accelerationHit,
        public readonly ?string $accelerationMode,
        public readonly ?string $engineType,
        public readonly int $durationMs,
        public readonly string $queryHash,
        public readonly array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->extra,
            'cache_hit' => $this->cacheHit,
            'semantic_layer_used' => $this->semanticLayerUsed,
            'permission_applied' => $this->permissionApplied,
            'acceleration_hit' => $this->accelerationHit,
            'acceleration_mode' => $this->accelerationMode,
            'engine_type' => $this->engineType,
            'duration_ms' => $this->durationMs,
            'query_hash' => $this->queryHash,
        ];
    }
}
