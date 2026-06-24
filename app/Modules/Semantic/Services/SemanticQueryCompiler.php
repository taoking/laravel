<?php

namespace App\Modules\Semantic\Services;

use App\Modules\Dataset\Models\Dataset;
use App\Modules\Semantic\DTO\SemanticQueryPlan;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Models\Metric;
use Illuminate\Validation\ValidationException;

class SemanticQueryCompiler
{
    public function __construct(private readonly MetricFormulaParser $formulaParser) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function usesSemanticLayer(array $payload): bool
    {
        return ($payload['semantic_metrics'] ?? []) !== [] || ($payload['semantic_dimensions'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function compile(array $payload): SemanticQueryPlan
    {
        $datasetId = (int) $payload['dataset_id'];
        $dataset = Dataset::query()->with('fields')->findOrFail($datasetId);
        $queryPayload = $payload;
        $metricVersions = [];
        $metricLabels = [];
        $queryMetrics = [];
        $compoundFormulas = [];
        $allMetricCodes = [];
        $visibleMetricCodes = $this->metricCodes($payload['semantic_metrics'] ?? []);
        $semanticDimensions = $this->compileDimensions($dataset, $payload['semantic_dimensions'] ?? []);

        foreach ($visibleMetricCodes as $code) {
            $metric = $this->metricByCode($dataset, $code);
            $this->expandMetric($dataset, $metric, $queryMetrics, $compoundFormulas, $metricVersions, $metricLabels, $allMetricCodes, []);
        }

        $queryPayload['dimensions'] = array_values(array_merge($payload['dimensions'] ?? [], $semanticDimensions));
        $queryPayload['metrics'] = array_values(array_merge($payload['metrics'] ?? [], array_values($queryMetrics)));
        unset($queryPayload['semantic_metrics'], $queryPayload['semantic_dimensions']);

        $dependencyMetricCodes = array_values(array_diff(array_unique($allMetricCodes), $visibleMetricCodes));

        return new SemanticQueryPlan(
            queryPayload: $queryPayload,
            semanticMetrics: collect($visibleMetricCodes)->map(fn (string $code): array => [
                'metric_code' => $code,
                'version' => $metricVersions[$code] ?? null,
            ])->all(),
            semanticDimensions: collect($semanticDimensions)->map(fn (array $dimension): array => [
                'dimension_code' => $dimension['alias'] ?? $dimension['field'],
                'field' => $dimension['field'],
                'time_granularity' => $dimension['time_granularity'] ?? null,
            ])->all(),
            metricVersions: $metricVersions,
            metricLabels: $metricLabels,
            compoundFormulas: $compoundFormulas,
            visibleMetrics: $visibleMetricCodes,
            dependencyMetricCodes: $dependencyMetricCodes,
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function applyResult(SemanticQueryPlan $plan, array $result): array
    {
        $rows = collect($result['rows'] ?? [])
            ->map(function (array $row) use ($plan): array {
                foreach ($plan->compoundFormulas as $code => $formula) {
                    $row[$code] = $this->formulaParser->evaluate($formula, $row);
                }

                foreach ($plan->dependencyMetricCodes as $code) {
                    unset($row[$code]);
                }

                return $row;
            })
            ->values()
            ->all();

        $columns = collect($result['columns'] ?? [])
            ->reject(fn (array $column): bool => in_array($column['name'] ?? null, $plan->dependencyMetricCodes, true))
            ->values();

        foreach ($plan->visibleMetrics as $code) {
            if (array_key_exists($code, $plan->compoundFormulas) && ! $columns->contains('name', $code)) {
                $columns->push([
                    'name' => $code,
                    'label' => $plan->metricLabels[$code] ?? $code,
                    'type' => 'number',
                ]);
            }
        }

        return [
            ...$result,
            'columns' => $columns->values()->all(),
            'rows' => $rows,
            'meta' => [
                ...($result['meta'] ?? []),
                'semantic_layer_used' => true,
                'semantic_metrics' => $plan->semanticMetrics,
                'semantic_dimensions' => $plan->semanticDimensions,
                'metric_versions' => $plan->metricVersions,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function context(SemanticQueryPlan $plan): array
    {
        return [
            'semantic_layer_used' => true,
            'semantic_metrics_json' => $plan->semanticMetrics,
            'semantic_dimensions_json' => $plan->semanticDimensions,
            'metric_versions_json' => $plan->metricVersions,
            'metric_versions_hash' => $plan->versionsHash(),
        ];
    }

    /**
     * @param  array<int, mixed>  $dimensions
     * @return list<array<string, mixed>>
     */
    private function compileDimensions(Dataset $dataset, array $dimensions): array
    {
        $compiled = [];

        foreach ($dimensions as $dimensionConfig) {
            $code = is_array($dimensionConfig)
                ? (string) ($dimensionConfig['dimension_code'] ?? '')
                : (string) $dimensionConfig;
            $dimension = Dimension::query()
                ->where('dataset_id', $dataset->id)
                ->where('code', $code)
                ->where('status', 'active')
                ->first();

            if ($dimension === null) {
                throw ValidationException::withMessages([
                    'semantic_dimensions' => ["Semantic dimension [{$code}] is not active or does not exist."],
                ]);
            }

            $compiled[] = [
                'field' => $dimension->field_name,
                'time_granularity' => is_array($dimensionConfig) ? ($dimensionConfig['time_granularity'] ?? null) : null,
                'alias' => $dimension->code,
            ];
        }

        return $compiled;
    }

    /**
     * @param  array<int, mixed>  $metrics
     * @return list<string>
     */
    private function metricCodes(array $metrics): array
    {
        return collect($metrics)
            ->map(fn (mixed $metric): ?string => is_array($metric) ? ($metric['metric_code'] ?? null) : (is_string($metric) ? $metric : null))
            ->filter(fn (?string $code): bool => $code !== null && $code !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function metricByCode(Dataset $dataset, string $code): Metric
    {
        $metric = Metric::query()
            ->where('dataset_id', $dataset->id)
            ->where('code', $code)
            ->whereIn('status', ['active', 'deprecated'])
            ->first();

        if ($metric === null) {
            throw ValidationException::withMessages([
                'semantic_metrics' => ["Semantic metric [{$code}] is not active or does not exist."],
            ]);
        }

        return $metric;
    }

    /**
     * @param  array<string, array<string, mixed>>  $queryMetrics
     * @param  array<string, string>  $compoundFormulas
     * @param  array<string, int>  $metricVersions
     * @param  array<string, string>  $metricLabels
     * @param  list<string>  $allMetricCodes
     * @param  list<string>  $stack
     */
    private function expandMetric(Dataset $dataset, Metric $metric, array &$queryMetrics, array &$compoundFormulas, array &$metricVersions, array &$metricLabels, array &$allMetricCodes, array $stack): void
    {
        if (in_array($metric->code, $stack, true)) {
            throw ValidationException::withMessages([
                'semantic_metrics' => ['Metric dependencies cannot contain a cycle.'],
            ]);
        }

        $allMetricCodes[] = $metric->code;
        $metricVersions[$metric->code] = (int) $metric->version;
        $metricLabels[$metric->code] = (string) $metric->name;

        if ($metric->metric_type === 'base') {
            $queryMetrics[$metric->code] = [
                'field' => $metric->source_field,
                'aggregate' => $metric->aggregate_function,
                'alias' => $metric->code,
            ];

            return;
        }

        foreach ($this->formulaParser->dependencies((string) $metric->formula) as $dependencyCode) {
            $dependency = $this->metricByCode($dataset, $dependencyCode);
            $this->expandMetric($dataset, $dependency, $queryMetrics, $compoundFormulas, $metricVersions, $metricLabels, $allMetricCodes, [...$stack, $metric->code]);
        }

        $compoundFormulas[$metric->code] = (string) $metric->formula;
    }
}
