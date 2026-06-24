<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AccelerationSchemaService
{
    public function __construct(private readonly ClickHouseTypeMapper $typeMapper) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function preview(Dataset $dataset): array
    {
        $dataset->loadMissing('fields');
        $partitionField = $this->partitionField($dataset->fields);
        $orderFields = $this->orderFields($dataset->fields, $partitionField);

        return $dataset->fields
            ->filter(fn (DatasetField $field): bool => IdentifierGuard::isSafe($field->field_name))
            ->values()
            ->map(function (DatasetField $field) use ($partitionField, $orderFields): array {
                $isPartitionKey = $partitionField?->id === $field->id;
                $isOrderKey = $orderFields->contains(fn (DatasetField $orderField): bool => $orderField->id === $field->id);
                $isNullable = ! ($isPartitionKey || $isOrderKey);

                return [
                    'dataset_field_id' => $field->id,
                    'source_field_name' => $field->field_name,
                    'target_field_name' => $field->field_name,
                    'source_type' => $field->normalized_type,
                    'target_type' => $this->typeMapper->clickHouseType($field, $isNullable),
                    'is_dimension' => (bool) $field->is_dimension,
                    'is_metric' => (bool) $field->is_metric,
                    'aggregate_functions_json' => $field->is_metric ? $this->aggregateFunctions($field) : [],
                    'is_partition_key' => $isPartitionKey,
                    'is_order_key' => $isOrderKey,
                    'is_nullable' => $isNullable,
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    public function syncColumns(AccelerationProfile $profile, array $columns = []): void
    {
        $profile->loadMissing('dataset.fields');
        $columns = $columns === [] ? $this->preview($profile->dataset) : $columns;
        $fieldsByName = $profile->dataset->fields->keyBy('field_name');
        $keptSources = [];

        foreach ($columns as $column) {
            $source = (string) ($column['source_field_name'] ?? '');
            $target = (string) ($column['target_field_name'] ?? $source);
            $field = $fieldsByName->get($source);

            if (! $field instanceof DatasetField) {
                throw ValidationException::withMessages([
                    'columns' => ["Source field [{$source}] does not belong to the dataset."],
                ]);
            }

            $this->assertIdentifier($source, 'source_field_name');
            $this->assertIdentifier($target, 'target_field_name');

            $keptSources[] = $source;
            $profile->columns()->updateOrCreate(
                ['source_field_name' => $source],
                [
                    'dataset_field_id' => $field->id,
                    'target_field_name' => $target,
                    'source_type' => (string) ($column['source_type'] ?? $field->normalized_type),
                    'target_type' => (string) ($column['target_type'] ?? $this->typeMapper->clickHouseType($field, (bool) ($column['is_nullable'] ?? true))),
                    'is_dimension' => (bool) ($column['is_dimension'] ?? $field->is_dimension),
                    'is_metric' => (bool) ($column['is_metric'] ?? $field->is_metric),
                    'aggregate_functions_json' => $column['aggregate_functions_json'] ?? ($field->is_metric ? $this->aggregateFunctions($field) : []),
                    'is_partition_key' => (bool) ($column['is_partition_key'] ?? false),
                    'is_order_key' => (bool) ($column['is_order_key'] ?? false),
                    'is_nullable' => (bool) ($column['is_nullable'] ?? true),
                ],
            );
        }

        $profile->columns()
            ->whereNotIn('source_field_name', $keptSources)
            ->delete();
    }

    private function assertIdentifier(string $identifier, string $field): void
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw ValidationException::withMessages([
                'columns' => ["{$field} [{$identifier}] is not allowed."],
            ]);
        }
    }

    /**
     * @param  Collection<int, DatasetField>  $fields
     */
    private function partitionField(Collection $fields): ?DatasetField
    {
        return $fields->first(fn (DatasetField $field): bool => in_array($field->normalized_type, ['date', 'datetime', 'timestamp'], true))
            ?? $fields->first(fn (DatasetField $field): bool => $field->semantic_type === 'time');
    }

    /**
     * @param  Collection<int, DatasetField>  $fields
     * @return Collection<int, DatasetField>
     */
    private function orderFields(Collection $fields, ?DatasetField $partitionField): Collection
    {
        return $fields
            ->filter(fn (DatasetField $field): bool => ($partitionField !== null && $field->id === $partitionField->id) || $field->is_dimension)
            ->take(3)
            ->values();
    }

    /**
     * @return list<string>
     */
    private function aggregateFunctions(DatasetField $field): array
    {
        if ($field->default_aggregate !== 'none') {
            return [$field->default_aggregate];
        }

        return in_array($field->normalized_type, ['integer', 'decimal', 'number'], true)
            ? ['sum', 'avg', 'min', 'max']
            : ['count', 'countDistinct'];
    }
}
