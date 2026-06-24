<?php

namespace App\Modules\Query\Services;

use App\Models\User;
use App\Modules\Acceleration\DTO\AccelerationRouteDecision;
use App\Modules\Acceleration\DTO\AggregateRouteDecision;
use App\Modules\Acceleration\Services\AccelerationDecisionPipeline;
use App\Modules\Acceleration\Services\AccelerationQueryExecutor;
use App\Modules\Acceleration\Services\AccelerationQueryRouter;
use App\Modules\Acceleration\Services\AggregateQueryRouter;
use App\Modules\DataPermission\DTO\PermissionCompileResult;
use App\Modules\DataPermission\DTO\PermissionContext;
use App\Modules\DataPermission\Services\PermissionCompiler;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Compilers\SqlCompiler;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryRequestDTO;
use App\Modules\Query\Validators\QueryRequestValidator;
use App\Modules\Semantic\Services\SemanticQueryCompiler;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class QueryService
{
    public function __construct(
        private readonly QueryRequestValidator $validator,
        private readonly SqlCompiler $sqlCompiler,
        private readonly QueryCacheService $cacheService,
        private readonly QueryExecutor $executor,
        private readonly QueryLogService $logService,
        private readonly AggregateQueryRouter $aggregateRouter,
        private readonly AccelerationQueryRouter $accelerationRouter,
        private readonly AccelerationQueryExecutor $accelerationExecutor,
        private readonly SemanticQueryCompiler $semanticQueryCompiler,
        private readonly PermissionCompiler $permissionCompiler,
        private readonly AccelerationDecisionPipeline $decisionPipeline,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array{elapsed_ms: int, cached: bool, total: int}}
     */
    public function execute(array $payload, ?User $user, array $cacheContext = []): array
    {
        if ($this->semanticQueryCompiler->usesSemanticLayer($payload)) {
            $plan = $this->semanticQueryCompiler->compile($payload);
            $result = $this->executeResolved($plan->queryPayload, $user, [
                ...$cacheContext,
                ...$this->semanticQueryCompiler->context($plan),
            ]);

            return $this->semanticQueryCompiler->applyResult($plan, $result);
        }

        return $this->executeResolved($payload, $user, $cacheContext);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array{elapsed_ms: int, cached: bool, total: int}}
     */
    private function executeResolved(array $payload, ?User $user, array $cacheContext = []): array
    {
        $query = QueryRequestDTO::fromArray($payload);
        $dataset = Dataset::query()
            ->with(['dataSource', 'fields'])
            ->findOrFail($query->datasetId);

        $requestSource = is_string($cacheContext['request_source'] ?? null) ? $cacheContext['request_source'] : 'api';
        $permission = $this->permissionCompiler->compile(new PermissionContext(
            dataset: $dataset,
            user: $user,
            requestSource: $requestSource,
            chartId: is_numeric($cacheContext['chart_id'] ?? null) ? (int) $cacheContext['chart_id'] : null,
            dashboardId: is_numeric($cacheContext['dashboard_id'] ?? null) ? (int) $cacheContext['dashboard_id'] : null,
        ));

        if (! $permission->resourceAllowed) {
            throw new AuthorizationException($permission->deniedReason ?? 'This action is unauthorized.');
        }

        $this->validator->validate($dataset, $query, $user);

        $compiledQuery = $this->sqlCompiler->compile($dataset, $query, $user);
        $sourceContext = $this->sourceContext($dataset);
        $plan = $this->accelerationRouter->plan(
            dataset: $dataset,
            query: $query,
            user: $user,
            permissionFilters: $permission->rowFilters,
            permission: $permission,
            requestSource: $requestSource,
            semanticLayerUsed: (bool) ($cacheContext['semantic_layer_used'] ?? false),
        );
        $decisionPipelineResult = $this->decisionPipeline->decide($plan);
        $aggregateDecision = $decisionPipelineResult->aggregateDecision;
        $cacheContext = [
            ...$cacheContext,
            ...$this->permissionContext($permission),
            'logical_plan_hash' => $plan->hash(),
            'query_mode' => $plan->queryMode(),
            'request_source' => $requestSource,
            'acceleration_decision' => $decisionPipelineResult->toArray(),
        ];
        $aggregateFallbackReason = null;

        if ($aggregateDecision->useAggregate && $aggregateDecision->definition !== null) {
            $aggregateQuery = null;

            try {
                $aggregateQuery = $this->aggregateRouter->compile($plan, $aggregateDecision->definition);
                $context = [
                    ...$cacheContext,
                    ...$sourceContext,
                    ...$this->aggregateContext($aggregateDecision, true),
                    'fallback_used' => false,
                    'detail_fallback_used' => false,
                ];

                if ($query->useCache) {
                    $cached = $this->cacheService->get($aggregateQuery, $user, $context);

                    if ($cached !== null) {
                        $this->logService->success($dataset, $user, $aggregateQuery, 0, count($cached['rows']), true, [
                            ...$context,
                            'accelerated_duration_ms' => 0,
                            'total_duration_ms' => 0,
                        ]);

                        return [
                            'columns' => $cached['columns'],
                            'rows' => $cached['rows'],
                            'meta' => [
                                ...$this->metaFromContext($context),
                                'elapsed_ms' => 0,
                                'cached' => true,
                                'total' => count($cached['rows']),
                                'accelerated_duration_ms' => 0,
                            ],
                        ];
                    }
                }

                $execution = $this->aggregateRouter->execute($aggregateDecision->definition, $aggregateQuery);
                $sourceDurationMs = $this->sourceComparisonDuration($dataset, $compiledQuery);
                $result = [
                    'columns' => $aggregateQuery->columns,
                    'rows' => $execution->rows,
                ];

                if ($query->useCache) {
                    $this->cacheService->put($aggregateQuery, $result, $user, $context);
                }

                $this->logService->success($dataset, $user, $aggregateQuery, $execution->elapsedMs, count($execution->rows), false, [
                    ...$context,
                    'source_duration_ms' => $sourceDurationMs,
                    'accelerated_duration_ms' => $execution->elapsedMs,
                    'total_duration_ms' => $execution->elapsedMs,
                ]);

                return [
                    ...$result,
                    'meta' => [
                        ...$this->metaFromContext($context),
                        'elapsed_ms' => $execution->elapsedMs,
                        'cached' => false,
                        'total' => count($execution->rows),
                        'source_duration_ms' => $sourceDurationMs,
                        'accelerated_duration_ms' => $execution->elapsedMs,
                    ],
                ];
            } catch (Throwable $exception) {
                if (! $aggregateDecision->fallbackAllowed) {
                    $this->logService->failure($dataset, $user, $aggregateQuery ?? $compiledQuery, $exception, context: [
                        ...$cacheContext,
                        ...$sourceContext,
                        ...$this->aggregateContext($aggregateDecision, false),
                        'fallback_used' => false,
                        'fallback_reason' => $exception->getMessage(),
                    ]);

                    throw $exception;
                }

                $aggregateFallbackReason = $exception->getMessage();
            }
        }

        $decision = $decisionPipelineResult->detailDecision;

        if ($aggregateFallbackReason !== null && $decision->reason === 'not_checked') {
            $decision = $this->accelerationRouter->decide($plan);
        }

        if ($decision->useAcceleration && $decision->profile !== null) {
            try {
                $acceleratedQuery = $this->accelerationExecutor->compile($plan, $decision->profile);
                $context = [
                    ...$cacheContext,
                    ...$sourceContext,
                    ...$this->accelerationContext($decision, true),
                    ...$this->aggregateFallbackContext($aggregateDecision, $aggregateFallbackReason, true),
                ];

                if ($query->useCache) {
                    $cached = $this->cacheService->get($acceleratedQuery, $user, $context);

                    if ($cached !== null) {
                        $this->logService->success($dataset, $user, $acceleratedQuery, 0, count($cached['rows']), true, [
                            ...$context,
                            'accelerated_duration_ms' => 0,
                            'total_duration_ms' => 0,
                        ]);

                        return [
                            'columns' => $cached['columns'],
                            'rows' => $cached['rows'],
                            'meta' => [
                                ...$this->metaFromContext($context),
                                'elapsed_ms' => 0,
                                'cached' => true,
                                'total' => count($cached['rows']),
                            ],
                        ];
                    }
                }

                $execution = $this->accelerationExecutor->execute($decision->profile, $acceleratedQuery);
                $sourceDurationMs = $this->sourceComparisonDuration($dataset, $compiledQuery);
                $result = [
                    'columns' => $acceleratedQuery->columns,
                    'rows' => $execution->rows,
                ];

                if ($query->useCache) {
                    $this->cacheService->put($acceleratedQuery, $result, $user, $context);
                }

                $this->logService->success($dataset, $user, $acceleratedQuery, $execution->elapsedMs, count($execution->rows), false, [
                    ...$context,
                    'source_duration_ms' => $sourceDurationMs,
                    'accelerated_duration_ms' => $execution->elapsedMs,
                    'total_duration_ms' => $execution->elapsedMs,
                ]);

                return [
                    ...$result,
                    'meta' => [
                        ...$this->metaFromContext($context),
                        'elapsed_ms' => $execution->elapsedMs,
                        'cached' => false,
                        'total' => count($execution->rows),
                        'source_duration_ms' => $sourceDurationMs,
                        'accelerated_duration_ms' => $execution->elapsedMs,
                    ],
                ];
            } catch (Throwable $exception) {
                if (! $decision->fallbackAllowed) {
                    $this->logService->failure($dataset, $user, $compiledQuery, $exception, context: [
                        ...$cacheContext,
                        ...$sourceContext,
                        ...$this->accelerationContext($decision, false),
                        ...$this->aggregateFallbackContext($aggregateDecision, $aggregateFallbackReason, true),
                        'fallback_used' => false,
                        'fallback_reason' => $exception->getMessage(),
                    ]);

                    throw $exception;
                }

                return $this->executeSource($dataset, $query, $user, $compiledQuery, [
                    ...$cacheContext,
                    ...$sourceContext,
                    ...$this->accelerationContext($decision, false),
                    ...$this->aggregateFallbackContext($aggregateDecision, $aggregateFallbackReason, true),
                    'fallback_used' => true,
                    'fallback_reason' => $exception->getMessage(),
                ]);
            }
        }

        return $this->executeSource($dataset, $query, $user, $compiledQuery, [
            ...$cacheContext,
            ...$sourceContext,
            ...$this->accelerationContext($decision, false),
            ...$this->aggregateFallbackContext($aggregateDecision, $aggregateFallbackReason, false),
            'fallback_used' => $aggregateFallbackReason !== null,
            'fallback_reason' => $aggregateFallbackReason !== null
                ? 'aggregate_table: '.$aggregateFallbackReason.'; detail_table: '.$decision->reason
                : $decision->reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cacheContext
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function executeSource(Dataset $dataset, QueryRequestDTO $query, ?User $user, CompiledQuery $compiledQuery, array $cacheContext): array
    {
        $cacheContext = [
            ...$cacheContext,
            ...$this->sourceContext($dataset),
        ];

        if ($query->useCache) {
            $cached = $this->cacheService->get($compiledQuery, $user, $cacheContext);

            if ($cached !== null) {
                $this->logService->success($dataset, $user, $compiledQuery, 0, count($cached['rows']), true, [
                    ...$cacheContext,
                    'total_duration_ms' => 0,
                ]);

                return [
                    'columns' => $cached['columns'],
                    'rows' => $cached['rows'],
                    'meta' => [
                        ...$this->metaFromContext($cacheContext),
                        'elapsed_ms' => 0,
                        'cached' => true,
                        'total' => count($cached['rows']),
                    ],
                ];
            }
        }

        try {
            $execution = $this->executor->execute($dataset, $compiledQuery);
        } catch (Throwable $exception) {
            $this->logService->failure($dataset, $user, $compiledQuery, $exception, context: $cacheContext);

            throw $exception;
        }

        $result = [
            'columns' => $compiledQuery->columns,
            'rows' => $execution->rows,
        ];

        if ($query->useCache) {
            $this->cacheService->put($compiledQuery, $result, $user, $cacheContext);
        }

        $this->logService->success($dataset, $user, $compiledQuery, $execution->elapsedMs, count($execution->rows), false, [
            ...$cacheContext,
            'raw_duration_ms' => $execution->elapsedMs,
            'total_duration_ms' => $execution->elapsedMs,
        ]);

        return [
            ...$result,
            'meta' => [
                ...$this->metaFromContext($cacheContext),
                'elapsed_ms' => $execution->elapsedMs,
                'cached' => false,
                'total' => count($execution->rows),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accelerationContext(AccelerationRouteDecision $decision, bool $hit): array
    {
        $context = [
            'acceleration_hit' => $hit,
            'acceleration_version' => $decision->profile?->version ?? 0,
        ];

        if ($decision->profile?->id !== null) {
            $context['acceleration_profile_id'] = $decision->profile->id;
        }

        if ($decision->engineType !== null) {
            $context['engine_type'] = $decision->engineType;
            $context['acceleration_engine'] = $decision->engineType;
        }

        if ($decision->mode !== null) {
            $context['acceleration_mode'] = $decision->mode;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateContext(AggregateRouteDecision $decision, bool $hit): array
    {
        return [
            'engine_type' => $decision->profile?->engine_type ?? 'clickhouse',
            'acceleration_hit' => $hit,
            'acceleration_profile_id' => $decision->profile?->id,
            'acceleration_engine' => $decision->profile?->engine_type ?? 'clickhouse',
            'acceleration_mode' => 'aggregate_table',
            'acceleration_version' => $decision->definition?->version ?? 0,
            'aggregate_definition_id' => $decision->definition?->id,
            'aggregate_table' => $decision->definition?->target_table,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateFallbackContext(AggregateRouteDecision $decision, ?string $reason, bool $detailFallbackUsed): array
    {
        if ($reason === null || $decision->definition === null) {
            return [
                'detail_fallback_used' => false,
            ];
        }

        return [
            'aggregate_definition_id' => $decision->definition->id,
            'aggregate_table' => $decision->definition->target_table,
            'detail_fallback_used' => $detailFallbackUsed,
            'fallback_used' => true,
            'fallback_reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function metaFromContext(array $context): array
    {
        return [
            'request_source' => $context['request_source'] ?? null,
            'query_mode' => $context['query_mode'] ?? null,
            'permission_hash' => $context['permission_hash'] ?? null,
            'logical_plan_hash' => $context['logical_plan_hash'] ?? null,
            'engine_type' => $context['engine_type'] ?? null,
            'data_source_type' => $context['data_source_type'] ?? null,
            'data_source_id' => $context['data_source_id'] ?? null,
            'semantic_layer_used' => (bool) ($context['semantic_layer_used'] ?? false),
            'semantic_metrics' => $context['semantic_metrics_json'] ?? null,
            'semantic_dimensions' => $context['semantic_dimensions_json'] ?? null,
            'metric_versions' => $context['metric_versions_json'] ?? null,
            'acceleration_hit' => (bool) ($context['acceleration_hit'] ?? false),
            'acceleration_profile_id' => $context['acceleration_profile_id'] ?? null,
            'acceleration_engine' => $context['acceleration_engine'] ?? null,
            'acceleration_mode' => $context['acceleration_mode'] ?? null,
            'aggregate_definition_id' => $context['aggregate_definition_id'] ?? null,
            'aggregate_table' => $context['aggregate_table'] ?? null,
            'fallback_used' => (bool) ($context['fallback_used'] ?? false),
            'fallback_reason' => $context['fallback_reason'] ?? null,
            'detail_fallback_used' => (bool) ($context['detail_fallback_used'] ?? false),
        ];
    }

    /**
     * @return array{engine_type: string|null, data_source_type: string|null, data_source_id: int|null}
     */
    private function sourceContext(Dataset $dataset): array
    {
        $dataset->loadMissing('dataSource');

        $dataSourceType = $dataset->dataSource?->type;

        return [
            'engine_type' => $dataset->dataSource?->type,
            'data_source_type' => $dataSourceType,
            'data_source_id' => $dataset->dataSource?->id !== null ? (int) $dataset->dataSource->id : null,
            ...($this->isOlapNative($dataSourceType) ? [
                'acceleration_hit' => false,
                'acceleration_engine' => $dataSourceType,
                'acceleration_mode' => 'olap_native',
            ] : []),
        ];
    }

    private function isOlapNative(?string $dataSourceType): bool
    {
        return in_array($dataSourceType, ['starrocks', 'doris', 'clickhouse'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function permissionContext(PermissionCompileResult $permission): array
    {
        return [
            'permission_hash' => $permission->permissionHash,
            'permission_applied' => $permission->rowFilters !== [] || $permission->columnRules !== [],
            'permission_filters' => array_map(fn ($filter): array => [
                'field' => $filter->field,
                'operator' => $filter->operator,
                'value' => $filter->value,
            ], $permission->rowFilters),
            'hidden_fields' => $permission->hiddenFields,
            'masked_fields' => $permission->maskedFields,
        ];
    }

    private function sourceComparisonDuration(Dataset $dataset, CompiledQuery $compiledQuery): ?int
    {
        if (! (bool) config('bi_acceleration.query.compare_original_query', false)) {
            return null;
        }

        try {
            return $this->executor->execute($dataset, $compiledQuery)->elapsedMs;
        } catch (Throwable) {
            return null;
        }
    }
}
