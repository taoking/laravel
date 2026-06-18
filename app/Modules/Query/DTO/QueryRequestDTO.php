<?php

namespace App\Modules\Query\DTO;

class QueryRequestDTO
{
    /**
     * @param  list<DimensionDTO>  $dimensions
     * @param  list<MetricDTO>  $metrics
     * @param  list<FilterDTO>  $filters
     * @param  list<SortDTO>  $sorts
     */
    public function __construct(
        public readonly int $datasetId,
        public readonly array $dimensions = [],
        public readonly array $metrics = [],
        public readonly array $filters = [],
        public readonly array $sorts = [],
        public readonly int $limit = 100,
        public readonly int $offset = 0,
        public readonly bool $useCache = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            datasetId: (int) $payload['dataset_id'],
            dimensions: collect($payload['dimensions'] ?? [])->map(fn (array $dimension): DimensionDTO => DimensionDTO::fromArray($dimension))->values()->all(),
            metrics: collect($payload['metrics'] ?? [])->map(fn (array $metric): MetricDTO => MetricDTO::fromArray($metric))->values()->all(),
            filters: collect($payload['filters'] ?? [])->map(fn (array $filter): FilterDTO => FilterDTO::fromArray($filter))->values()->all(),
            sorts: collect($payload['sorts'] ?? [])->map(fn (array $sort): SortDTO => SortDTO::fromArray($sort))->values()->all(),
            limit: min(max((int) ($payload['limit'] ?? 100), 1), 1000),
            offset: max((int) ($payload['offset'] ?? 0), 0),
            useCache: (bool) ($payload['use_cache'] ?? false),
        );
    }
}
