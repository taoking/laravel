<?php

namespace App\Modules\Acceleration\DTO;

use App\Models\User;
use App\Modules\DataPermission\DTO\PermissionCompileResult;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\FilterDTO;
use App\Modules\Query\DTO\QueryRequestDTO;

class LogicalQueryPlan
{
    /**
     * @param  list<FilterDTO>  $permissionFilters
     */
    public function __construct(
        public readonly Dataset $dataset,
        public readonly QueryRequestDTO $query,
        public readonly ?User $user,
        public readonly array $permissionFilters = [],
        public readonly ?PermissionCompileResult $permission = null,
        public readonly string $requestSource = 'api',
        public readonly bool $semanticLayerUsed = false,
    ) {}

    public function datasetId(): int
    {
        return (int) $this->dataset->id;
    }

    public function dataSourceId(): ?int
    {
        return $this->dataset->data_source_id !== null ? (int) $this->dataset->data_source_id : null;
    }

    public function dataSourceType(): ?string
    {
        $this->dataset->loadMissing('dataSource');

        return $this->dataset->dataSource?->type;
    }

    public function queryMode(): string
    {
        if ($this->query->rawFields !== []) {
            return 'raw_field';
        }

        if ($this->semanticLayerUsed) {
            return $this->query->dimensions !== [] || $this->query->metrics !== [] ? 'semantic_metric' : 'mixed';
        }

        return 'raw_field';
    }

    /**
     * @return list<string>
     */
    public function requiredFields(): array
    {
        $fields = [];

        foreach ($this->query->rawFields as $field) {
            $fields[] = $field;
        }

        foreach ($this->query->dimensions as $dimension) {
            $fields[] = $dimension->field;
        }

        foreach ($this->query->metrics as $metric) {
            $fields[] = $metric->field;
        }

        foreach ([...$this->query->filters, ...$this->permissionFilters] as $filter) {
            $fields[] = $filter->field;
        }

        foreach ($this->query->sorts as $sort) {
            $fields[] = $sort->field;
        }

        return array_values(array_unique(array_filter($fields)));
    }

    public function hash(): string
    {
        return sha1(json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dataset_id' => $this->datasetId(),
            'data_source_id' => $this->dataSourceId(),
            'data_source_type' => $this->dataSourceType(),
            'request_source' => $this->requestSource,
            'query_mode' => $this->queryMode(),
            'table_refs' => [$this->dataset->main_table],
            'select_dimensions' => array_map(fn ($dimension): array => [
                'field' => $dimension->field,
                'alias' => $dimension->alias,
                'time_granularity' => $dimension->timeGranularity,
            ], $this->query->dimensions),
            'select_metrics' => array_map(fn ($metric): array => [
                'field' => $metric->field,
                'aggregate' => $metric->aggregate,
                'alias' => $metric->alias,
            ], $this->query->metrics),
            'raw_fields' => $this->query->rawFields,
            'filters' => array_map(fn (FilterDTO $filter): array => [
                'field' => $filter->field,
                'operator' => $filter->operator,
                'value' => $filter->value,
            ], $this->query->filters),
            'permission_filters' => array_map(fn (FilterDTO $filter): array => [
                'field' => $filter->field,
                'operator' => $filter->operator,
                'value' => $filter->value,
            ], $this->permissionFilters),
            'sorts' => array_map(fn ($sort): array => [
                'field' => $sort->field,
                'direction' => $sort->direction,
            ], $this->query->sorts),
            'limit' => $this->query->limit,
            'offset' => $this->query->offset,
            'group_by' => array_map(fn ($dimension): string => $dimension->alias ?? $dimension->field, $this->query->dimensions),
            'semantic_layer_used' => $this->semanticLayerUsed,
            'required_fields' => $this->requiredFields(),
            'column_visibility' => [
                'hidden_fields' => $this->permission?->hiddenFields ?? [],
                'masked_fields' => $this->permission?->maskedFields ?? [],
            ],
            'permission_hash' => $this->permission?->permissionHash,
        ];
    }
}
