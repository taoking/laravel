<?php

namespace App\Modules\Query\Services;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\Models\QueryLog;
use Throwable;

class QueryLogService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function success(Dataset $dataset, ?User $user, CompiledQuery $query, int $elapsedMs, int $rowCount, bool $cached, array $context = []): void
    {
        $dataset->loadMissing('dataSource');

        QueryLog::query()->create([
            'tenant_id' => $dataset->tenant_id,
            'user_id' => $user?->id,
            'dataset_id' => $dataset->id,
            'chart_id' => $context['chart_id'] ?? null,
            'dashboard_id' => $context['dashboard_id'] ?? null,
            'request_source' => $context['request_source'] ?? null,
            'query_mode' => $context['query_mode'] ?? null,
            'query_hash' => $query->hash,
            'logical_plan_hash' => $context['logical_plan_hash'] ?? null,
            'permission_hash' => $context['permission_hash'] ?? null,
            'permission_applied' => (bool) ($context['permission_applied'] ?? false),
            'sql' => $query->sql,
            'bindings_json' => $query->bindings,
            'elapsed_ms' => $elapsedMs,
            'row_count' => $rowCount,
            'cached' => $cached,
            'is_slow' => $this->isSlow($elapsedMs),
            'status' => 'success',
            ...$this->accelerationAttributes($dataset, $context),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function failure(Dataset $dataset, ?User $user, CompiledQuery $query, Throwable $exception, int $elapsedMs = 0, array $context = []): void
    {
        $dataset->loadMissing('dataSource');

        QueryLog::query()->create([
            'tenant_id' => $dataset->tenant_id,
            'user_id' => $user?->id,
            'dataset_id' => $dataset->id,
            'chart_id' => $context['chart_id'] ?? null,
            'dashboard_id' => $context['dashboard_id'] ?? null,
            'request_source' => $context['request_source'] ?? null,
            'query_mode' => $context['query_mode'] ?? null,
            'query_hash' => $query->hash,
            'logical_plan_hash' => $context['logical_plan_hash'] ?? null,
            'permission_hash' => $context['permission_hash'] ?? null,
            'permission_applied' => (bool) ($context['permission_applied'] ?? false),
            'sql' => $query->sql,
            'bindings_json' => $query->bindings,
            'elapsed_ms' => $elapsedMs,
            'row_count' => 0,
            'cached' => false,
            'is_slow' => $this->isSlow($elapsedMs),
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            ...$this->accelerationAttributes($dataset, $context),
        ]);
    }

    private function isSlow(int $elapsedMs): bool
    {
        return $elapsedMs >= (int) config('audit.slow_query_threshold_ms', 3000);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function accelerationAttributes(Dataset $dataset, array $context): array
    {
        $dataSourceType = $context['data_source_type'] ?? $dataset->dataSource?->type;

        return [
            'engine_type' => $context['engine_type'] ?? $context['acceleration_engine'] ?? $dataSourceType,
            'data_source_type' => $dataSourceType,
            'semantic_layer_used' => (bool) ($context['semantic_layer_used'] ?? false),
            'semantic_metrics_json' => $context['semantic_metrics_json'] ?? null,
            'semantic_dimensions_json' => $context['semantic_dimensions_json'] ?? null,
            'metric_versions_json' => $context['metric_versions_json'] ?? null,
            'acceleration_hit' => (bool) ($context['acceleration_hit'] ?? false),
            'acceleration_profile_id' => $context['acceleration_profile_id'] ?? null,
            'acceleration_engine' => $context['acceleration_engine'] ?? null,
            'acceleration_mode' => $context['acceleration_mode'] ?? null,
            'fallback_used' => (bool) ($context['fallback_used'] ?? false),
            'fallback_reason' => $context['fallback_reason'] ?? null,
            'raw_duration_ms' => $context['raw_duration_ms'] ?? $context['source_duration_ms'] ?? null,
            'source_duration_ms' => $context['source_duration_ms'] ?? null,
            'accelerated_duration_ms' => $context['accelerated_duration_ms'] ?? null,
            'total_duration_ms' => $context['total_duration_ms'] ?? null,
            'aggregate_definition_id' => $context['aggregate_definition_id'] ?? null,
            'aggregate_table' => $context['aggregate_table'] ?? null,
            'detail_fallback_used' => (bool) ($context['detail_fallback_used'] ?? false),
        ];
    }
}
