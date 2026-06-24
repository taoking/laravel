<?php

namespace App\Modules\Acceleration\DTO;

class AccelerationDecision
{
    /**
     * @param  list<AccelerationCandidate>  $candidates
     * @param  list<string>  $fallbackChain
     */
    public function __construct(
        public readonly bool $useAcceleration,
        public readonly string $accelerationMode,
        public readonly ?string $engineType,
        public readonly ?int $profileId,
        public readonly ?int $aggregateDefinitionId,
        public readonly string $reason,
        public readonly bool $fallbackAllowed,
        public readonly array $fallbackChain,
        public readonly ?string $cacheKeySuffix,
        public readonly ?int $estimatedCost,
        public readonly AggregateRouteDecision $aggregateDecision,
        public readonly AccelerationRouteDecision $detailDecision,
        public readonly array $candidates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'use_acceleration' => $this->useAcceleration,
            'acceleration_mode' => $this->accelerationMode,
            'engine_type' => $this->engineType,
            'profile_id' => $this->profileId,
            'aggregate_definition_id' => $this->aggregateDefinitionId,
            'reason' => $this->reason,
            'fallback_allowed' => $this->fallbackAllowed,
            'fallback_chain' => $this->fallbackChain,
            'cache_key_suffix' => $this->cacheKeySuffix,
            'estimated_cost' => $this->estimatedCost,
            'candidates' => array_map(fn (AccelerationCandidate $candidate): array => $candidate->toArray(), $this->candidates),
        ];
    }
}
