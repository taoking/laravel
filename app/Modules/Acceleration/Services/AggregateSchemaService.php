<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationColumn;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AggregateSchemaService
{
    public function __construct(private readonly ClickHouseTypeMapper $typeMapper) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function preview(AccelerationAggregateDefinition $definition): array
    {
        $definition->loadMissing(['dataset.fields', 'detailProfile.columns']);

        /** @var Collection<string, DatasetField> $fieldsByName */
        $fieldsByName = $definition->dataset->fields->keyBy('field_name');
        /** @var Collection<string, AccelerationColumn> $detailColumnsBySource */
        $detailColumnsBySource = $definition->detailProfile->columns->keyBy('source_field_name');
        $columns = [];

        if ($definition->time_field !== null && $definition->time_grain !== 'none') {
            $field = $this->field($fieldsByName, $definition->time_field);
            $this->assertDetailColumn($detailColumnsBySource, $definition->time_field);
            $columns[] = [
                'source_field_name' => $definition->time_field,
                'target_field_name' => $this->timeTargetField($definition->time_field, $definition->time_grain),
                'column_role' => 'time_grain',
                'aggregate_function' => 'none',
                'source_type' => $field->normalized_type,
                'target_type' => $this->timeTargetType($definition->time_grain),
            ];
        }

        foreach ($this->dimensions($definition) as $dimension) {
            $field = $this->field($fieldsByName, $dimension);
            $detailColumn = $this->assertDetailColumn($detailColumnsBySource, $dimension);
            $this->assertIdentifier($dimension, 'dimensions');

            $columns[] = [
                'source_field_name' => $dimension,
                'target_field_name' => $dimension,
                'column_role' => 'dimension',
                'aggregate_function' => 'none',
                'source_type' => $field->normalized_type,
                'target_type' => $detailColumn->target_type ?: $this->typeMapper->clickHouseType($field),
            ];
        }

        foreach ($this->metrics($definition) as $metric) {
            $source = (string) $metric['field'];
            $aggregate = (string) $metric['aggregate'];
            $field = $this->field($fieldsByName, $source);
            $detailColumn = $this->assertDetailColumn($detailColumnsBySource, $source);
            $target = (string) ($metric['alias'] ?? "{$source}_{$aggregate}");
            $this->assertIdentifier($target, 'metrics');

            $columns[] = [
                'source_field_name' => $source,
                'target_field_name' => $target,
                'column_role' => 'metric',
                'aggregate_function' => $aggregate,
                'source_type' => $field->normalized_type,
                'target_type' => $this->metricTargetType($field, $detailColumn, $aggregate),
            ];
        }

        return $columns;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    public function syncColumns(AccelerationAggregateDefinition $definition, array $columns = []): void
    {
        $columns = $columns === [] ? $this->preview($definition) : $columns;
        $keptTargets = [];

        foreach ($columns as $column) {
            $target = (string) ($column['target_field_name'] ?? '');
            $source = isset($column['source_field_name']) ? (string) $column['source_field_name'] : null;
            $this->assertIdentifier($target, 'target_field_name');

            if ($source !== null && $source !== '') {
                $this->assertIdentifier($source, 'source_field_name');
            }

            $keptTargets[] = $target;
            $definition->columns()->updateOrCreate(
                ['target_field_name' => $target],
                [
                    'source_field_name' => $source,
                    'column_role' => (string) ($column['column_role'] ?? 'dimension'),
                    'aggregate_function' => (string) ($column['aggregate_function'] ?? 'none'),
                    'source_type' => (string) ($column['source_type'] ?? 'unknown'),
                    'target_type' => (string) ($column['target_type'] ?? 'String'),
                ],
            );
        }

        $definition->columns()
            ->whereNotIn('target_field_name', $keptTargets)
            ->delete();
    }

    private function timeTargetField(string $field, string $grain): string
    {
        $this->assertIdentifier($field, 'time_field');

        return "{$field}_{$grain}";
    }

    private function timeTargetType(string $grain): string
    {
        return match ($grain) {
            'year' => 'UInt16',
            default => 'Date',
        };
    }

    private function metricTargetType(DatasetField $field, AccelerationColumn $detailColumn, string $aggregate): string
    {
        return match ($aggregate) {
            'count', 'countDistinct' => 'UInt64',
            'avg', 'sum' => 'Float64',
            'min', 'max' => $detailColumn->target_type ?: $this->typeMapper->clickHouseType($field),
            default => 'Float64',
        };
    }

    /**
     * @param  Collection<string, DatasetField>  $fieldsByName
     */
    private function field(Collection $fieldsByName, string $source): DatasetField
    {
        $field = $fieldsByName->get($source);

        if (! $field instanceof DatasetField) {
            throw ValidationException::withMessages([
                'fields' => ["Source field [{$source}] does not belong to the dataset."],
            ]);
        }

        return $field;
    }

    /**
     * @param  Collection<string, AccelerationColumn>  $detailColumnsBySource
     */
    private function assertDetailColumn(Collection $detailColumnsBySource, string $source): AccelerationColumn
    {
        $column = $detailColumnsBySource->get($source);

        if (! $column instanceof AccelerationColumn) {
            throw ValidationException::withMessages([
                'detail_profile_id' => ["Detail acceleration column mapping missing for [{$source}]."],
            ]);
        }

        return $column;
    }

    /**
     * @return list<string>
     */
    private function dimensions(AccelerationAggregateDefinition $definition): array
    {
        return collect($definition->dimensions_json ?? [])
            ->map(fn (mixed $dimension): string => is_array($dimension) ? (string) ($dimension['field'] ?? '') : (string) $dimension)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{field: string, aggregate: string, alias?: string}>
     */
    private function metrics(AccelerationAggregateDefinition $definition): array
    {
        return collect($definition->metrics_json ?? [])
            ->map(fn (mixed $metric): array => is_array($metric) ? $metric : [])
            ->filter(fn (array $metric): bool => isset($metric['field'], $metric['aggregate']))
            ->map(fn (array $metric): array => [
                'field' => (string) $metric['field'],
                'aggregate' => (string) $metric['aggregate'],
                ...(isset($metric['alias']) ? ['alias' => (string) $metric['alias']] : []),
            ])
            ->values()
            ->all();
    }

    private function assertIdentifier(string $identifier, string $field): void
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw ValidationException::withMessages([
                $field => ["Identifier [{$identifier}] is not allowed."],
            ]);
        }
    }
}
