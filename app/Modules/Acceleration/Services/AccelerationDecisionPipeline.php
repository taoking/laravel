<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\DTO\AccelerationCandidate;
use App\Modules\Acceleration\DTO\AccelerationDecision;
use App\Modules\Acceleration\DTO\AccelerationRouteDecision;
use App\Modules\Acceleration\DTO\AggregateRouteDecision;
use App\Modules\Acceleration\DTO\LogicalQueryPlan;

class AccelerationDecisionPipeline
{
    public function __construct(
        private readonly AggregateQueryRouter $aggregateRouter,
        private readonly AccelerationQueryRouter $detailRouter,
    ) {}

    public function decide(LogicalQueryPlan $plan): AccelerationDecision
    {
        $dataSourceType = $plan->dataSourceType();

        if (in_array($dataSourceType, ['starrocks', 'doris', 'clickhouse'], true)) {
            $aggregateDecision = AggregateRouteDecision::miss('olap_native_source', false);
            $detailDecision = AccelerationRouteDecision::miss('olap_native_source', false);
            $candidate = new AccelerationCandidate('olap_native', true, 'source_is_olap_engine', $dataSourceType);

            return new AccelerationDecision(
                useAcceleration: false,
                accelerationMode: 'olap_native',
                engineType: $dataSourceType,
                profileId: null,
                aggregateDefinitionId: null,
                reason: 'source_is_olap_engine',
                fallbackAllowed: false,
                fallbackChain: [],
                cacheKeySuffix: 'olap_native:'.$dataSourceType,
                estimatedCost: null,
                aggregateDecision: $aggregateDecision,
                detailDecision: $detailDecision,
                candidates: [$candidate],
            );
        }

        $aggregateDecision = $this->aggregateRouter->decide($plan);
        $detailDecision = AccelerationRouteDecision::miss('not_checked');
        $candidates = [
            new AccelerationCandidate(
                mode: 'aggregate_table',
                eligible: $aggregateDecision->useAggregate,
                reason: $aggregateDecision->reason,
                engineType: $aggregateDecision->profile?->engine_type ?? 'clickhouse',
                profileId: $aggregateDecision->profile?->id,
                aggregateDefinitionId: $aggregateDecision->definition?->id,
            ),
        ];

        if ($aggregateDecision->useAggregate) {
            $candidates[] = new AccelerationCandidate('detail_table', false, 'not_checked_aggregate_selected');
            $candidates[] = new AccelerationCandidate('raw', true, 'raw_source_available', $dataSourceType);

            return new AccelerationDecision(
                useAcceleration: true,
                accelerationMode: 'aggregate_table',
                engineType: $aggregateDecision->profile?->engine_type ?? 'clickhouse',
                profileId: $aggregateDecision->profile?->id,
                aggregateDefinitionId: $aggregateDecision->definition?->id,
                reason: $aggregateDecision->reason,
                fallbackAllowed: $aggregateDecision->fallbackAllowed,
                fallbackChain: ['detail_table', 'raw'],
                cacheKeySuffix: 'aggregate:'.($aggregateDecision->definition?->id ?? 'none').':v:'.($aggregateDecision->definition?->version ?? 0),
                estimatedCost: null,
                aggregateDecision: $aggregateDecision,
                detailDecision: $detailDecision,
                candidates: $candidates,
            );
        }

        $detailDecision = $this->detailRouter->decide($plan);
        $candidates[] = new AccelerationCandidate(
            mode: 'detail_table',
            eligible: $detailDecision->useAcceleration,
            reason: $detailDecision->reason,
            engineType: $detailDecision->engineType,
            profileId: $detailDecision->profile?->id,
        );
        $candidates[] = new AccelerationCandidate('raw', true, 'raw_source_available', $dataSourceType);

        if ($detailDecision->useAcceleration) {
            return new AccelerationDecision(
                useAcceleration: true,
                accelerationMode: 'detail_table',
                engineType: $detailDecision->engineType,
                profileId: $detailDecision->profile?->id,
                aggregateDefinitionId: null,
                reason: $detailDecision->reason,
                fallbackAllowed: $detailDecision->fallbackAllowed,
                fallbackChain: ['raw'],
                cacheKeySuffix: 'detail:'.($detailDecision->profile?->id ?? 'none').':v:'.($detailDecision->profile?->version ?? 0),
                estimatedCost: null,
                aggregateDecision: $aggregateDecision,
                detailDecision: $detailDecision,
                candidates: $candidates,
            );
        }

        return new AccelerationDecision(
            useAcceleration: false,
            accelerationMode: 'raw',
            engineType: $dataSourceType,
            profileId: null,
            aggregateDefinitionId: null,
            reason: $detailDecision->reason !== 'no_active_profile' ? $detailDecision->reason : $aggregateDecision->reason,
            fallbackAllowed: true,
            fallbackChain: [],
            cacheKeySuffix: 'raw:'.$dataSourceType,
            estimatedCost: null,
            aggregateDecision: $aggregateDecision,
            detailDecision: $detailDecision,
            candidates: $candidates,
        );
    }
}
