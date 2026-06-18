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
        QueryLog::query()->create([
            'tenant_id' => $dataset->tenant_id,
            'user_id' => $user?->id,
            'dataset_id' => $dataset->id,
            'chart_id' => $context['chart_id'] ?? null,
            'dashboard_id' => $context['dashboard_id'] ?? null,
            'query_hash' => $query->hash,
            'sql' => $query->sql,
            'bindings_json' => $query->bindings,
            'elapsed_ms' => $elapsedMs,
            'row_count' => $rowCount,
            'cached' => $cached,
            'is_slow' => $this->isSlow($elapsedMs),
            'status' => 'success',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function failure(Dataset $dataset, ?User $user, CompiledQuery $query, Throwable $exception, int $elapsedMs = 0, array $context = []): void
    {
        QueryLog::query()->create([
            'tenant_id' => $dataset->tenant_id,
            'user_id' => $user?->id,
            'dataset_id' => $dataset->id,
            'chart_id' => $context['chart_id'] ?? null,
            'dashboard_id' => $context['dashboard_id'] ?? null,
            'query_hash' => $query->hash,
            'sql' => $query->sql,
            'bindings_json' => $query->bindings,
            'elapsed_ms' => $elapsedMs,
            'row_count' => 0,
            'cached' => false,
            'is_slow' => $this->isSlow($elapsedMs),
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }

    private function isSlow(int $elapsedMs): bool
    {
        return $elapsedMs >= (int) config('audit.slow_query_threshold_ms', 3000);
    }
}
