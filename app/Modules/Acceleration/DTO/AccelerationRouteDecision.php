<?php

namespace App\Modules\Acceleration\DTO;

use App\Modules\Acceleration\Models\AccelerationProfile;

class AccelerationRouteDecision
{
    public function __construct(
        public readonly bool $useAcceleration,
        public readonly ?AccelerationProfile $profile = null,
        public readonly ?string $engineType = null,
        public readonly ?string $mode = null,
        public readonly string $reason = 'not_checked',
        public readonly bool $fallbackAllowed = true,
    ) {}

    public static function miss(string $reason, bool $fallbackAllowed = true): self
    {
        return new self(false, reason: $reason, fallbackAllowed: $fallbackAllowed);
    }

    public static function hit(AccelerationProfile $profile, string $reason = 'matched'): self
    {
        return new self(
            useAcceleration: true,
            profile: $profile,
            engineType: $profile->engine_type,
            mode: $profile->mode,
            reason: $reason,
            fallbackAllowed: (bool) config('bi_acceleration.query.fallback_on_error', true),
        );
    }
}
