<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\DTO\AggregateRouteDecision;
use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryExecutionResult;

class AggregateQueryRouter
{
    public function __construct(
        private readonly AggregateEligibilityChecker $eligibilityChecker,
        private readonly AggregateSqlGenerator $sqlGenerator,
        private readonly ClickHouseClient $client,
    ) {}

    public function decide(LogicalQueryPlan $plan): AggregateRouteDecision
    {
        if (! (bool) config('bi_acceleration.aggregate.enabled', true)) {
            return AggregateRouteDecision::miss('aggregate_acceleration_disabled');
        }

        $definitions = AccelerationAggregateDefinition::query()
            ->with(['columns', 'detailProfile', 'aggregateProfile', 'dataset.fields'])
            ->where('dataset_id', $plan->dataset->id)
            ->where('status', 'active')
            ->latest('updated_at')
            ->get();

        if ($definitions->isEmpty()) {
            return AggregateRouteDecision::miss('no_active_aggregate_definition');
        }

        foreach ($definitions as $definition) {
            $reason = $this->eligibilityChecker->missReason($definition, $plan);

            if ($reason === null) {
                return AggregateRouteDecision::hit($definition);
            }
        }

        return AggregateRouteDecision::miss($reason ?? 'no_matching_aggregate_definition');
    }

    public function compile(LogicalQueryPlan $plan, AccelerationAggregateDefinition $definition): CompiledQuery
    {
        return $this->sqlGenerator->generate($plan, $definition);
    }

    public function execute(AccelerationAggregateDefinition $definition, CompiledQuery $query): QueryExecutionResult
    {
        $startedAt = microtime(true);
        $rows = $this->client->select($query->sql, $this->sqlGenerator->database($definition));

        return new QueryExecutionResult(
            rows: $rows,
            elapsedMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
