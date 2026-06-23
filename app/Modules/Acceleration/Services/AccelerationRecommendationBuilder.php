<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;

class AccelerationRecommendationBuilder
{
    private const SUPPORTED_AGGREGATES = ['sum', 'avg', 'count', 'countDistinct', 'min', 'max'];

    /**
     * @return array<string, mixed>|null
     */
    public function aggregateDraftFromChart(Chart $chart): ?array
    {
        $chart->loadMissing('dataset.fields');
        $dataset = $chart->dataset;
        $fieldsByName = $dataset->fields->keyBy('field_name');
        $config = $chart->config_json ?? [];
        $dimensions = [];
        $filters = [];
        $metrics = [];
        $timeField = null;
        $timeGrain = 'none';

        foreach ($config['dimensions'] ?? [] as $dimension) {
            if (! is_array($dimension) || ! isset($dimension['field'])) {
                continue;
            }

            $fieldName = (string) $dimension['field'];

            if (! $fieldsByName->has($fieldName)) {
                continue;
            }

            $grain = isset($dimension['time_granularity']) ? (string) $dimension['time_granularity'] : null;

            if ($grain !== null && in_array($grain, ['day', 'month', 'year'], true)) {
                $timeField = $fieldName;
                $timeGrain = $grain;

                continue;
            }

            if ($grain !== null) {
                return null;
            }

            $dimensions[] = $fieldName;
        }

        foreach ($config['metrics'] ?? [] as $metric) {
            if (! is_array($metric) || ! isset($metric['field'])) {
                continue;
            }

            $fieldName = (string) $metric['field'];
            $field = $fieldsByName->get($fieldName);

            if ($field === null) {
                continue;
            }

            $aggregate = (string) ($metric['aggregate'] ?? ($field->default_aggregate !== 'none' ? $field->default_aggregate : 'count'));

            if (! in_array($aggregate, self::SUPPORTED_AGGREGATES, true)) {
                continue;
            }

            $metrics[] = [
                'field' => $fieldName,
                'aggregate' => $aggregate,
                ...(isset($metric['alias']) ? ['alias' => (string) $metric['alias']] : []),
            ];
        }

        foreach ($config['filters'] ?? [] as $filter) {
            if (! is_array($filter) || ! isset($filter['field'], $filter['operator'])) {
                continue;
            }

            $fieldName = (string) $filter['field'];

            if (! $fieldsByName->has($fieldName)) {
                continue;
            }

            $filters[] = [
                'field' => $fieldName,
                'operator' => (string) $filter['operator'],
                ...(array_key_exists('value', $filter) ? ['value' => $filter['value']] : []),
            ];

            if ($fieldName !== $timeField && ! in_array($fieldName, $dimensions, true)) {
                $dimensions[] = $fieldName;
            }
        }

        $dimensions = array_values(array_unique($dimensions));

        if ($metrics === [] || count($dimensions) > (int) config('bi_acceleration.recommendation.max_dimensions', 5) || count($metrics) > (int) config('bi_acceleration.recommendation.max_metrics', 10)) {
            return null;
        }

        return [
            'dataset_id' => (int) $dataset->id,
            'chart_id' => (int) $chart->id,
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'filters' => $filters,
            'time_field' => $timeField,
            'time_grain' => $timeGrain,
        ];
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array<string, mixed>>  $metrics
     */
    public function activeAggregateCovers(Dataset $dataset, array $dimensions, array $metrics, ?string $timeField, ?string $timeGrain): bool
    {
        return AccelerationAggregateDefinition::query()
            ->where('dataset_id', $dataset->id)
            ->where('status', 'active')
            ->get()
            ->contains(fn (AccelerationAggregateDefinition $definition): bool => $this->sameDraft($definition, $dimensions, $metrics, $timeField, $timeGrain));
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array<string, mixed>>  $metrics
     */
    public function sameDraft(AccelerationAggregateDefinition $definition, array $dimensions, array $metrics, ?string $timeField, ?string $timeGrain): bool
    {
        return $this->normalizeDimensions($definition->dimensions_json ?? []) === $this->normalizeDimensions($dimensions)
            && $this->normalizeMetrics($definition->metrics_json ?? []) === $this->normalizeMetrics($metrics)
            && ($definition->time_field ?? null) === $timeField
            && ($definition->time_grain ?? 'none') === ($timeGrain ?: 'none');
    }

    /**
     * @param  list<string|array<string, mixed>>  $dimensions
     * @return list<string>
     */
    public function normalizeDimensions(array $dimensions): array
    {
        $values = collect($dimensions)
            ->map(fn (mixed $dimension): string => is_array($dimension) ? (string) ($dimension['field'] ?? '') : (string) $dimension)
            ->filter()
            ->unique()
            ->values()
            ->all();

        sort($values);

        return $values;
    }

    /**
     * @param  list<array<string, mixed>>  $metrics
     * @return list<array{field: string, aggregate: string, alias?: string}>
     */
    public function normalizeMetrics(array $metrics): array
    {
        $values = collect($metrics)
            ->map(fn (array $metric): array => [
                'field' => (string) ($metric['field'] ?? ''),
                'aggregate' => (string) ($metric['aggregate'] ?? 'count'),
                ...(isset($metric['alias']) ? ['alias' => (string) $metric['alias']] : []),
            ])
            ->filter(fn (array $metric): bool => $metric['field'] !== '')
            ->sortBy(fn (array $metric): string => $metric['field'].'|'.$metric['aggregate'].'|'.($metric['alias'] ?? ''))
            ->values()
            ->all();

        return $values;
    }
}
