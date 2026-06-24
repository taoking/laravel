<?php

namespace App\Modules\Semantic\DTO;

class SemanticQueryPlan
{
    /**
     * @param  array<string, mixed>  $queryPayload
     * @param  list<array<string, mixed>>  $semanticMetrics
     * @param  list<array<string, mixed>>  $semanticDimensions
     * @param  array<string, int>  $metricVersions
     * @param  array<string, string>  $metricLabels
     * @param  array<string, string>  $compoundFormulas
     * @param  list<string>  $visibleMetrics
     * @param  list<string>  $dependencyMetricCodes
     */
    public function __construct(
        public readonly array $queryPayload,
        public readonly array $semanticMetrics,
        public readonly array $semanticDimensions,
        public readonly array $metricVersions,
        public readonly array $metricLabels,
        public readonly array $compoundFormulas,
        public readonly array $visibleMetrics,
        public readonly array $dependencyMetricCodes,
    ) {}

    public function versionsHash(): string
    {
        return sha1(json_encode($this->metricVersions, JSON_THROW_ON_ERROR));
    }
}
