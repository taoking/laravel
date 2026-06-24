<?php

namespace App\Modules\Query\Services;

use App\Models\User;
use App\Modules\Acceleration\DTO\AccelerationRouteDecision;
use App\Modules\Acceleration\DTO\AggregateRouteDecision;
use App\Modules\Acceleration\Services\AccelerationQueryExecutor;
use App\Modules\Acceleration\Services\AccelerationQueryRouter;
use App\Modules\Acceleration\Services\AggregateQueryRouter;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Compilers\SqlCompiler;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryRequestDTO;
use App\Modules\Query\Validators\QueryRequestValidator;
use App\Modules\Semantic\Services\SemanticQueryCompiler;
use Throwable;

class QueryService
{
    public function __construct(
        private readonly QueryRequestValidator $validator,
        private readonly SqlCompiler $sqlCompiler,
        private readonly QueryCacheService $cacheService,
        private readonly QueryExecutor $executor,
        private readonly QueryLogService $logService,
        private readonly DataPermissionService $dataPermissionService,
        private readonly AggregateQueryRouter $aggregateRouter,
        private readonly AccelerationQueryRouter $accelerationRouter,
        private readonly AccelerationQueryExecutor $accelerationExecutor,
        private readonly SemanticQueryCompiler $semanticQueryCompiler,
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

        $this->dataPermissionService->assertCanAccessDataset($dataset, $user);
        $this->validator->validate($dataset, $query, $user);

        $compiledQuery = $this->sqlCompiler->compile($dataset, $query, $user);
        $sourceContext = $this->sourceContext($dataset);
        $plan = $this->accelerationRouter->plan($dataset, $query, $user);
        $aggregateDecision = $this->aggregateRouter->decide($plan);
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

        $decision = $this->accelerationRouter->decide($plan);

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
                $this->logService->success($dataset, $user, $compiledQuery, 0, count($cached['rows']), true, $cacheContext);

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

        $this->logService->success($dataset, $user, $compiledQuery, $execution->elapsedMs, count($execution->rows), false, $cacheContext);

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
            'acceleration_profile_id' => $decision->profile?->id,
            'acceleration_engine' => $decision->engineType,
            'acceleration_mode' => $decision->mode,
            'acceleration_version' => $decision->profile?->version ?? 0,
        ];

        if ($decision->engineType !== null) {
            $context['engine_type'] = $decision->engineType;
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

        return [
            'engine_type' => $dataset->dataSource?->type,
            'data_source_type' => $dataset->dataSource?->type,
            'data_source_id' => $dataset->dataSource?->id !== null ? (int) $dataset->dataSource->id : null,
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
