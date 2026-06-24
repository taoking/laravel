<?php

namespace App\Modules\Metadata\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class MetadataChangeGuardService
{
    /**
     * @var list<string>
     */
    private const BLOCKING_RISKS = ['high', 'critical'];

    public function __construct(
        private readonly MetadataLifecycleService $lifecycleService,
        private readonly MetadataImpactAnalysisService $impactAnalysisService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function guardDelete(string $assetType, int $assetId, ?User $actor, bool $force = false): ?array
    {
        $this->lifecycleService->sync($assetType, $assetId);

        try {
            $impact = $this->impactAnalysisService->analyze($assetType, $assetId, 'delete', $actor);
        } catch (ModelNotFoundException) {
            return null;
        }

        if (! $force && in_array($impact['risk_level'], self::BLOCKING_RISKS, true)) {
            throw ValidationException::withMessages([
                'force' => [
                    'This delete operation has '.$impact['risk_level'].' metadata impact. Review impact analysis or pass force=true to confirm.',
                ],
                'risk_level' => [(string) $impact['risk_level']],
                'affected' => [$this->affectedSummary($impact)],
            ]);
        }

        return $impact;
    }

    /**
     * @param  array<string, mixed>  $impact
     */
    private function affectedSummary(array $impact): string
    {
        return collect([
            'datasets' => count($impact['affected_datasets'] ?? []),
            'metrics' => count($impact['affected_metrics'] ?? []),
            'charts' => count($impact['affected_charts'] ?? []),
            'dashboards' => count($impact['affected_dashboards'] ?? []),
            'acceleration_profiles' => count($impact['affected_acceleration_profiles'] ?? []),
        ])
            ->map(fn (int $count, string $name): string => $name.':'.$count)
            ->implode(', ');
    }
}
