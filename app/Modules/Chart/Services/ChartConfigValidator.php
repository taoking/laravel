<?php

namespace App\Modules\Chart\Services;

use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\QueryRequestDTO;
use App\Modules\Query\Validators\QueryRequestValidator;
use App\Modules\Semantic\Services\SemanticQueryCompiler;
use Illuminate\Validation\ValidationException;

class ChartConfigValidator
{
    public const CHART_TYPES = ['metric_card', 'bar', 'line', 'pie', 'table'];

    public function __construct(
        private readonly QueryRequestValidator $queryRequestValidator,
        private readonly SemanticQueryCompiler $semanticQueryCompiler,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function validate(Dataset $dataset, string $chartType, array $config): void
    {
        if (! in_array($chartType, self::CHART_TYPES, true)) {
            throw ValidationException::withMessages([
                'chart_type' => ['The selected chart type is not supported.'],
            ]);
        }

        $validationConfig = $this->semanticQueryCompiler->usesSemanticLayer($config)
            ? $this->semanticQueryCompiler->compile([
                ...$config,
                'dataset_id' => $dataset->id,
            ])->queryPayload
            : $config;

        $dimensions = $validationConfig['dimensions'] ?? [];
        $metrics = $validationConfig['metrics'] ?? [];

        match ($chartType) {
            'metric_card' => $this->requireMetric($metrics),
            'bar', 'line', 'pie' => $this->requireDimensionAndMetric($dimensions, $metrics),
            'table' => $this->requireAnyField($dimensions, $metrics),
        };

        $this->queryRequestValidator->validate(
            $dataset->loadMissing('fields'),
            QueryRequestDTO::fromArray([
                ...$validationConfig,
                'dataset_id' => $dataset->id,
            ]),
        );
    }

    /**
     * @param  array<int, mixed>  $metrics
     */
    private function requireMetric(array $metrics): void
    {
        if ($metrics === []) {
            throw ValidationException::withMessages([
                'config_json.metrics' => ['Metric card charts require at least one metric.'],
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $dimensions
     * @param  array<int, mixed>  $metrics
     */
    private function requireDimensionAndMetric(array $dimensions, array $metrics): void
    {
        if ($dimensions === [] || $metrics === []) {
            throw ValidationException::withMessages([
                'config_json' => ['This chart type requires at least one dimension and one metric.'],
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $dimensions
     * @param  array<int, mixed>  $metrics
     */
    private function requireAnyField(array $dimensions, array $metrics): void
    {
        if ($dimensions === [] && $metrics === []) {
            throw ValidationException::withMessages([
                'config_json' => ['Table charts require at least one dimension or metric.'],
            ]);
        }
    }
}
