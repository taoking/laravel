<?php

namespace App\Modules\Chart\Services;

class ChartQueryBuilder
{
    private const OVERRIDE_KEYS = ['filters', 'sorts', 'limit', 'offset', 'use_cache'];

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function build(int $datasetId, array $config, array $overrides = []): array
    {
        $allowedOverrides = collect($overrides)
            ->only(self::OVERRIDE_KEYS)
            ->all();
        $filters = array_merge($config['filters'] ?? [], $allowedOverrides['filters'] ?? []);
        unset($allowedOverrides['filters']);

        return [
            ...$config,
            ...$allowedOverrides,
            'filters' => $filters,
            'dataset_id' => $datasetId,
        ];
    }
}
