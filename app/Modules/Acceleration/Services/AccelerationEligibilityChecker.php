<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationProfile;
use Throwable;

class AccelerationEligibilityChecker
{
    public function __construct(private readonly AccelerationDriverManager $driverManager) {}

    public function missReason(AccelerationProfile $profile, LogicalQueryPlan $plan): ?string
    {
        if ($plan->query->rawFields !== []) {
            return 'raw_fields_not_supported_by_detail_acceleration';
        }

        if (! (bool) config('bi_acceleration.enabled', true)) {
            return 'acceleration_disabled';
        }

        if ($profile->engine_type !== 'clickhouse') {
            return 'engine_not_supported';
        }

        if ($profile->mode !== 'detail_table') {
            return 'mode_not_supported';
        }

        if ($profile->status !== 'active') {
            return 'profile_not_active';
        }

        try {
            $driver = $this->driverManager->driver($profile);
        } catch (Throwable) {
            return 'engine_not_supported';
        }

        if (! $driver->supports($plan, $profile)) {
            return 'driver_does_not_support_plan';
        }

        if (! $this->allFieldsMapped($profile, $plan)) {
            return 'field_mapping_missing';
        }

        try {
            if (! $driver->tableExists($profile)) {
                return 'target_table_missing';
            }
        } catch (Throwable) {
            return 'acceleration_connection_failed';
        }

        return null;
    }

    private function allFieldsMapped(AccelerationProfile $profile, LogicalQueryPlan $plan): bool
    {
        $mapped = $profile->columns->pluck('target_field_name', 'source_field_name');
        $required = collect();

        foreach ($plan->query->dimensions as $dimension) {
            $required->push($dimension->field);
        }

        foreach ($plan->query->metrics as $metric) {
            $required->push($metric->field);
        }

        foreach ([...$plan->query->filters, ...$plan->permissionFilters] as $filter) {
            $required->push($filter->field);
        }

        return $required
            ->unique()
            ->every(fn (string $field): bool => $mapped->has($field));
    }
}
