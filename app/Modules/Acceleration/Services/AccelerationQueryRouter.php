<?php

namespace App\Modules\Acceleration\Services;

use App\Models\User;
use App\Modules\Acceleration\DTO\AccelerationRouteDecision;
use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\DataPermission\DTO\PermissionCompileResult;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\FilterDTO;
use App\Modules\Query\DTO\QueryRequestDTO;

class AccelerationQueryRouter
{
    public function __construct(
        private readonly DataPermissionService $dataPermissionService,
        private readonly AccelerationEligibilityChecker $eligibilityChecker,
    ) {}

    /**
     * @param  list<FilterDTO>|null  $permissionFilters
     */
    public function plan(Dataset $dataset, QueryRequestDTO $query, ?User $user, ?array $permissionFilters = null, ?PermissionCompileResult $permission = null, string $requestSource = 'api', bool $semanticLayerUsed = false): LogicalQueryPlan
    {
        $permissionFilters ??= collect($this->dataPermissionService->rowRules($dataset, $user))
            ->map(fn ($rule): FilterDTO => new FilterDTO($rule->field_name, $rule->operator, $rule->ruleValue($user)))
            ->values()
            ->all();

        return new LogicalQueryPlan($dataset, $query, $user, $permissionFilters, $permission, $requestSource, $semanticLayerUsed);
    }

    public function decide(LogicalQueryPlan $plan): AccelerationRouteDecision
    {
        if (! (bool) config('bi_acceleration.enabled', true)) {
            return AccelerationRouteDecision::miss('acceleration_disabled');
        }

        $profiles = AccelerationProfile::query()
            ->with('columns')
            ->where('dataset_id', $plan->dataset->id)
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->get();

        if ($profiles->isEmpty()) {
            return AccelerationRouteDecision::miss('no_active_profile');
        }

        foreach ($profiles as $profile) {
            $reason = $this->eligibilityChecker->missReason($profile, $plan);

            if ($reason === null) {
                return AccelerationRouteDecision::hit($profile);
            }
        }

        return AccelerationRouteDecision::miss($reason ?? 'no_matching_profile');
    }
}
