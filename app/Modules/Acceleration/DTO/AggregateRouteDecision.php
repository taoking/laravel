<?php

namespace App\Modules\Acceleration\DTO;

use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;

class AggregateRouteDecision
{
    public function __construct(
        public readonly bool $useAggregate,
        public readonly ?AccelerationAggregateDefinition $definition = null,
        public readonly ?AccelerationProfile $profile = null,
        public readonly string $reason = 'not_checked',
        public readonly bool $fallbackAllowed = true,
    ) {}

    public static function miss(string $reason, bool $fallbackAllowed = true): self
    {
        return new self(false, reason: $reason, fallbackAllowed: $fallbackAllowed);
    }

    public static function hit(AccelerationAggregateDefinition $definition, string $reason = 'matched'): self
    {
        return new self(
            useAggregate: true,
            definition: $definition,
            profile: $definition->aggregateProfile,
            reason: $reason,
            fallbackAllowed: (bool) config('bi_acceleration.aggregate.fallback_to_detail', true),
        );
    }
}
