<?php

namespace App\Modules\Query\Services;

use App\Models\User;
use App\Modules\Acceleration\Services\AccelerationDecisionPipeline;
use App\Modules\Acceleration\Services\AccelerationQueryRouter;
use App\Modules\DataPermission\DTO\PermissionContext;
use App\Modules\DataPermission\Services\PermissionCompiler;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Compilers\SqlCompiler;
use App\Modules\Query\DTO\QueryContext;
use App\Modules\Query\DTO\QueryRequestDTO;
use App\Modules\Query\Validators\QueryRequestValidator;
use App\Modules\Semantic\DTO\SemanticQueryPlan;
use App\Modules\Semantic\Services\SemanticQueryCompiler;
use Illuminate\Auth\Access\AuthorizationException;

class QueryOrchestrator
{
    public function __construct(
        private readonly QueryService $queryService,
        private readonly SemanticQueryCompiler $semanticQueryCompiler,
        private readonly PermissionCompiler $permissionCompiler,
        private readonly QueryRequestValidator $validator,
        private readonly SqlCompiler $sqlCompiler,
        private readonly AccelerationQueryRouter $accelerationRouter,
        private readonly AccelerationDecisionPipeline $decisionPipeline,
        private readonly QueryCacheService $cacheService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(array $payload, ?User $user, array $context = []): array
    {
        return $this->queryService->execute($payload, $user, $context);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function debug(array $payload, ?User $user, array $context = []): array
    {
        $requestSource = is_string($context['request_source'] ?? null) ? $context['request_source'] : 'debug';
        $semanticPlan = null;
        $resolvedPayload = $payload;
        $semanticContext = [];

        if ($this->semanticQueryCompiler->usesSemanticLayer($payload)) {
            $semanticPlan = $this->semanticQueryCompiler->compile($payload);
            $resolvedPayload = $semanticPlan->queryPayload;
            $semanticContext = $this->semanticQueryCompiler->context($semanticPlan);
        }

        $query = QueryRequestDTO::fromArray($resolvedPayload);
        $dataset = Dataset::query()
            ->with(['dataSource', 'fields'])
            ->findOrFail($query->datasetId);
        $permission = $this->permissionCompiler->compile(new PermissionContext(
            dataset: $dataset,
            user: $user,
            requestSource: $requestSource,
            chartId: is_numeric($context['chart_id'] ?? null) ? (int) $context['chart_id'] : null,
            dashboardId: is_numeric($context['dashboard_id'] ?? null) ? (int) $context['dashboard_id'] : null,
        ));

        if (! $permission->resourceAllowed) {
            throw new AuthorizationException($permission->deniedReason ?? 'This action is unauthorized.');
        }

        $this->validator->validate($dataset, $query, $user);

        $compiled = $this->sqlCompiler->compile($dataset, $query, $user);
        $plan = $this->accelerationRouter->plan(
            dataset: $dataset,
            query: $query,
            user: $user,
            permissionFilters: $permission->rowFilters,
            permission: $permission,
            requestSource: $requestSource,
            semanticLayerUsed: $semanticPlan instanceof SemanticQueryPlan,
        );
        $decision = $this->decisionPipeline->decide($plan);
        $queryContext = new QueryContext(
            userId: $user?->id,
            requestSource: $requestSource,
            datasetId: (int) $dataset->id,
            chartId: is_numeric($context['chart_id'] ?? null) ? (int) $context['chart_id'] : null,
            dashboardId: is_numeric($context['dashboard_id'] ?? null) ? (int) $context['dashboard_id'] : null,
            dataSourceId: $dataset->dataSource?->id !== null ? (int) $dataset->dataSource->id : null,
            dataSourceType: $dataset->dataSource?->type,
            queryMode: $plan->queryMode(),
            semanticLayerUsed: $semanticPlan instanceof SemanticQueryPlan,
            cacheEnabled: $query->useCache,
            accelerationEnabled: (bool) config('bi_acceleration.enabled', true),
            permissionEnabled: true,
            debugEnabled: true,
        );
        $debugContext = [
            ...$context,
            ...$semanticContext,
            'permission_hash' => $permission->permissionHash,
            'query_mode' => $plan->queryMode(),
            'request_source' => $requestSource,
            'logical_plan_hash' => $plan->hash(),
            'engine_type' => $dataset->dataSource?->type,
            'data_source_type' => $dataset->dataSource?->type,
            'data_source_id' => $dataset->dataSource?->id,
            'acceleration_hit' => $decision->useAcceleration,
            'acceleration_mode' => $decision->accelerationMode,
            'acceleration_profile_id' => $decision->profileId,
            'aggregate_definition_id' => $decision->aggregateDefinitionId,
        ];

        return [
            'context' => $queryContext->toArray(),
            'semantic_compile_result' => $this->semanticDebug($semanticPlan),
            'permission_compile_result' => $permission->toArray(),
            'logical_plan' => [
                ...$plan->toArray(),
                'hash' => $plan->hash(),
            ],
            'acceleration_decision' => $decision->toArray(),
            'generated_sql' => $compiled->sql,
            'bindings' => $compiled->bindings,
            'query_hash' => $compiled->hash,
            'cache_key' => $this->cacheService->keyFor($compiled, $user, $debugContext),
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function semanticDebug(?SemanticQueryPlan $plan): array
    {
        if (! $plan instanceof SemanticQueryPlan) {
            return [
                'used' => false,
                'base_metrics' => [],
                'computed_metrics' => [],
                'dependencies' => [],
                'metric_versions' => [],
                'errors' => [],
            ];
        }

        return [
            'used' => true,
            'base_metrics' => array_values($plan->queryPayload['metrics'] ?? []),
            'computed_metrics' => collect($plan->compoundFormulas)
                ->map(fn (string $formula, string $code): array => [
                    'metric_code' => $code,
                    'formula' => $formula,
                ])
                ->values()
                ->all(),
            'dependencies' => $plan->dependencyMetricCodes,
            'metric_versions' => $plan->metricVersions,
            'semantic_metrics' => $plan->semanticMetrics,
            'semantic_dimensions' => $plan->semanticDimensions,
            'errors' => [],
        ];
    }
}
