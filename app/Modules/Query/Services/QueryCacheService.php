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

        if (is_numeric($chartId)) {
            return $this->keyBuilder->chartQuery((int) $chartId, $query->hash, $scope);
        }

        return $this->keyBuilder->query($query->hash, $scope);
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
