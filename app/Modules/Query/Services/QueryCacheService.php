<?php

namespace App\Modules\Query\Services;

use App\Models\User;
use App\Modules\Cache\Services\CacheKeyBuilder;
use App\Modules\Query\DTO\CompiledQuery;
use Illuminate\Support\Facades\Cache;

class QueryCacheService
{
    public function __construct(private readonly CacheKeyBuilder $keyBuilder) {}

    /**
     * @return array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>}|null
     */
    public function get(CompiledQuery $query, ?User $user = null, array $context = []): ?array
    {
        $cached = Cache::get($this->key($query, $user, $context));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array{columns: list<array{name: string, label: string, type: string}>, rows: list<array<string, mixed>>}  $result
     */
    public function put(CompiledQuery $query, array $result, ?User $user = null, array $context = []): void
    {
        $key = $this->key($query, $user, $context);

        Cache::put($key, $result, now()->addMinutes(5));
        $this->indexChartKey($context, $key);
    }

    public function keyFor(CompiledQuery $query, ?User $user = null, array $context = []): string
    {
        return $this->key($query, $user, $context);
    }

    public function forgetChart(int $chartId): void
    {
        $indexKey = $this->keyBuilder->chartQueryIndex($chartId);
        $keys = Cache::get($indexKey, []);

        if (is_array($keys)) {
            foreach ($keys as $key) {
                Cache::forget((string) $key);
            }
        }

        Cache::forget($indexKey);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function key(CompiledQuery $query, ?User $user, array $context): string
    {
        $scope = $user !== null ? 'user:'.$user->id : 'guest';
        $chartId = $context['chart_id'] ?? null;
        $accelerationHit = (bool) ($context['acceleration_hit'] ?? false);
        $profileId = is_numeric($context['acceleration_profile_id'] ?? null) ? (int) $context['acceleration_profile_id'] : null;
        $version = is_numeric($context['acceleration_version'] ?? null) ? (int) $context['acceleration_version'] : 0;
        $aggregateDefinitionId = ($accelerationHit && ($context['acceleration_mode'] ?? null) === 'aggregate_table' && is_numeric($context['aggregate_definition_id'] ?? null))
            ? (int) $context['aggregate_definition_id']
            : null;
        $engineType = is_string($context['engine_type'] ?? null) ? $context['engine_type'] : null;
        $dataSourceId = is_numeric($context['data_source_id'] ?? null) ? (int) $context['data_source_id'] : null;
        $metricVersionsHash = is_string($context['metric_versions_hash'] ?? null) ? $context['metric_versions_hash'] : null;
        $permissionHash = is_string($context['permission_hash'] ?? null) ? $context['permission_hash'] : null;
        $queryMode = is_string($context['query_mode'] ?? null) ? $context['query_mode'] : 'raw_field';
        $accelerationMode = is_string($context['acceleration_mode'] ?? null) ? $context['acceleration_mode'] : null;

        if (is_numeric($chartId)) {
            return $this->keyBuilder->chartQuery((int) $chartId, $query->hash, $scope, $accelerationHit, $profileId, $version, $aggregateDefinitionId, $engineType, $dataSourceId, $metricVersionsHash, $permissionHash, $queryMode, $accelerationMode);
        }

        return $this->keyBuilder->query($query->hash, $scope, $accelerationHit, $profileId, $version, $aggregateDefinitionId, $engineType, $dataSourceId, $metricVersionsHash, $permissionHash, $queryMode, $accelerationMode);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function indexChartKey(array $context, string $key): void
    {
        $chartId = $context['chart_id'] ?? null;

        if (! is_numeric($chartId)) {
            return;
        }

        $indexKey = $this->keyBuilder->chartQueryIndex((int) $chartId);
        $keys = Cache::get($indexKey, []);
        $keys = is_array($keys) ? $keys : [];
        $keys[] = $key;

        Cache::put($indexKey, array_values(array_unique($keys)), now()->addDay());
    }
}
