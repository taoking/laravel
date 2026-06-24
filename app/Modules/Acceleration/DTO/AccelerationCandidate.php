<?php

namespace App\Modules\Acceleration\DTO;

class AccelerationCandidate
{
    public function __construct(
        public readonly string $mode,
        public readonly bool $eligible,
        public readonly string $reason,
        public readonly ?string $engineType = null,
        public readonly ?int $profileId = null,
        public readonly ?int $aggregateDefinitionId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'eligible' => $this->eligible,
            'reason' => $this->reason,
            'engine_type' => $this->engineType,
            'profile_id' => $this->profileId,
            'aggregate_definition_id' => $this->aggregateDefinitionId,
        ];
    }
}
