<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Query\DTO\MetricDTO;
use Illuminate\Support\Collection;
use Throwable;

class AggregateEligibilityChecker
{
    public function __construct(
        private readonly ClickHouseClient $client,
        private readonly AggregateSqlGenerator $sqlGenerator,
    ) {}

    public function missReason(AccelerationAggregateDefinition $definition, LogicalQueryPlan $plan): ?string
    {
        if ($plan->query->rawFields !== []) {
            return 'raw_fields_not_supported_by_aggregate';
        }

        if (! (bool) config('bi_acceleration.enabled', true) || ! (bool) config('bi_acceleration.aggregate.enabled', true)) {
            return 'aggregate_acceleration_disabled';
        }

        $definition->loadMissing(['columns', 'detailProfile', 'aggregateProfile', 'dataset.fields']);

        if ($definition->status !== 'active') {
            return 'aggregate_definition_not_active';
        }

        if ($definition->target_table === null || $definition->target_table === '') {
            return 'aggregate_target_table_missing';
        }

        if (! $definition->aggregateProfile instanceof AccelerationProfile || $definition->aggregateProfile->status !== 'active') {
            return 'aggregate_profile_not_active';
        }

        if ($definition->aggregateProfile->engine_type !== 'clickhouse' || $definition->aggregateProfile->mode !== 'aggregate_table') {
            return 'aggregate_profile_not_supported';
        }

        if (! $definition->detailProfile instanceof AccelerationProfile || $definition->detailProfile->status !== 'active') {
            return 'detail_profile_not_active';
        }

        $dimensionFields = collect($definition->dimensions_json ?? [])->map(fn (mixed $dimension): string => is_array($dimension) ? (string) ($dimension['field'] ?? '') : (string) $dimension)->filter()->values();
        $metricDefinitions = collect($definition->metrics_json ?? [])->map(fn (mixed $metric): array => is_array($metric) ? $metric : [])->filter(fn (array $metric): bool => isset($metric['field'], $metric['aggregate']));
        $fieldsByName = $definition->dataset->fields->keyBy('field_name');
        $definitionGroupKeys = $this->definitionGroupKeys($definition, $dimensionFields);
        $queryGroupKeys = [];

        foreach ($plan->query->dimensions as $dimension) {
            if ($definition->time_field === $dimension->field && $dimension->timeGranularity !== null) {
                if ($definition->time_grain === 'none' || $dimension->timeGranularity !== $definition->time_grain) {
                    return 'time_grain_not_matched';
                }

                $queryGroupKeys[] = $this->timeKey($dimension->field, $dimension->timeGranularity);

                continue;
            }

            if (! $dimensionFields->contains($dimension->field)) {
                return 'dimension_not_covered';
            }

            $queryGroupKeys[] = $this->dimensionKey($dimension->field);
        }

        foreach ($plan->query->metrics as $metric) {
            $aggregate = $this->metricAggregate($metric, $fieldsByName);

            if (! $metricDefinitions->contains(fn (array $definitionMetric): bool => (string) $definitionMetric['field'] === $metric->field && (string) $definitionMetric['aggregate'] === $aggregate)) {
                return 'metric_not_covered';
            }

            if (in_array($aggregate, ['avg', 'countDistinct'], true) && ! $this->sameKeys($queryGroupKeys, $definitionGroupKeys)) {
                return 'non_additive_metric_requires_exact_grain';
            }
        }

        foreach ([...$plan->query->filters, ...$plan->permissionFilters] as $filter) {
            if ($definition->time_field === $filter->field && $definition->time_grain !== 'none') {
                continue;
            }

            if (! $dimensionFields->contains($filter->field)) {
                return 'filter_field_not_aggregated';
            }
        }

        try {
            $rows = $this->client->select($this->sqlGenerator->existsSql($definition), $this->sqlGenerator->database($definition));
            $first = $rows[0] ?? [];
            $value = $first['result'] ?? $first['exists'] ?? reset($first);

            if ((int) $value !== 1) {
                return 'aggregate_table_missing';
            }
        } catch (Throwable) {
            return 'aggregate_connection_failed';
        }

        return null;
    }

    /**
     * @param  Collection<int, string>  $dimensionFields
     * @return list<string>
     */
    private function definitionGroupKeys(AccelerationAggregateDefinition $definition, Collection $dimensionFields): array
    {
        $keys = $dimensionFields
            ->map(fn (string $field): string => $this->dimensionKey($field))
            ->values()
            ->all();

        if ($definition->time_field !== null && $definition->time_grain !== 'none') {
            $keys[] = $this->timeKey($definition->time_field, $definition->time_grain);
        }

        return $keys;
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function sameKeys(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }

    private function metricAggregate(MetricDTO $metric, Collection $fieldsByName): string
    {
        $field = $fieldsByName->get($metric->field);

        if (! $field instanceof DatasetField) {
            return $metric->aggregate ?? 'count';
        }

        return $metric->aggregate ?? ($field->default_aggregate !== 'none' ? $field->default_aggregate : 'count');
    }

    private function dimensionKey(string $field): string
    {
        return "dimension:{$field}";
    }

    private function timeKey(string $field, string $grain): string
    {
        return "time:{$field}:{$grain}";
    }
}
