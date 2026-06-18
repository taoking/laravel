<?php

namespace App\Modules\Dataset\Services;

use App\Models\User;
use App\Modules\Cache\Services\DatasetCacheService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Dataset\Models\DatasetTable;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceMetadataService;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DatasetService
{
    public function __construct(
        private readonly DataSourceMetadataService $metadataService,
        private readonly DatasetFieldClassifier $fieldClassifier,
        private readonly DatasetCacheService $datasetCacheService,
    ) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return Dataset::query()
            ->with('dataSource')
            ->withCount(['fields', 'tables'])
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): Dataset
    {
        $dataSource = DataSource::query()->findOrFail($payload['data_source_id']);
        $mainTable = (string) $payload['main_table'];
        $this->assertSafeTable($dataSource, $mainTable);

        return DB::transaction(function () use ($payload, $actor, $dataSource, $mainTable): Dataset {
            $dataset = Dataset::query()->create([
                'tenant_id' => $payload['tenant_id'] ?? null,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'data_source_id' => $dataSource->id,
                'dataset_type' => $payload['dataset_type'] ?? 'single_table',
                'main_table' => $mainTable,
                'config_json' => $payload['config_json'] ?? null,
                'status' => $payload['status'] ?? 'active',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            DatasetTable::query()->create([
                'dataset_id' => $dataset->id,
                'data_source_id' => $dataSource->id,
                'table_name' => $mainTable,
                'alias' => $payload['table_alias'] ?? $mainTable,
                'sort_order' => 0,
            ]);

            $this->syncFields($dataset);

            return $dataset->load(['dataSource', 'tables', 'fields']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Dataset $dataset, array $payload, ?User $actor): Dataset
    {
        $updatedDataset = DB::transaction(function () use ($dataset, $payload, $actor): Dataset {
            $tableAlias = Arr::pull($payload, 'table_alias');
            $dataSource = $dataset->dataSource;

            if (array_key_exists('data_source_id', $payload)) {
                $dataSource = DataSource::query()->findOrFail($payload['data_source_id']);
            }

            $mainTableChanged = array_key_exists('main_table', $payload) && $payload['main_table'] !== $dataset->main_table;
            $dataSourceChanged = $dataSource->id !== $dataset->data_source_id;

            if ($mainTableChanged || $dataSourceChanged) {
                $this->assertSafeTable($dataSource, (string) ($payload['main_table'] ?? $dataset->main_table));
            }

            $dataset->fill([
                ...$payload,
                'data_source_id' => $dataSource->id,
                'updated_by' => $actor?->id,
            ]);
            $dataset->save();

            if ($mainTableChanged || $dataSourceChanged) {
                $dataset->tables()->delete();
                $dataset->fields()->delete();

                DatasetTable::query()->create([
                    'dataset_id' => $dataset->id,
                    'data_source_id' => $dataSource->id,
                    'table_name' => $dataset->main_table,
                    'alias' => $tableAlias ?? $dataset->main_table,
                    'sort_order' => 0,
                ]);

                $this->syncFields($dataset->refresh());
            }

            return $dataset->refresh()->load(['dataSource', 'tables', 'fields']);
        });

        $this->datasetCacheService->forget($updatedDataset);

        return $updatedDataset;
    }

    public function delete(Dataset $dataset): void
    {
        $this->datasetCacheService->forget($dataset);

        DB::transaction(function () use ($dataset): void {
            $dataset->filters()->delete();
            $dataset->fields()->delete();
            $dataset->tables()->delete();
            $dataset->delete();
        });
    }

    /**
     * @return array{fields: int}
     */
    public function syncFields(Dataset $dataset): array
    {
        $dataset->loadMissing('dataSource');
        $this->assertSafeTable($dataset->dataSource, $dataset->main_table);

        $sourceFields = $this->metadataService->fields($dataset->dataSource, $dataset->main_table);
        $seenFieldNames = [];

        DB::transaction(function () use ($dataset, $sourceFields, &$seenFieldNames): void {
            foreach ($sourceFields as $sourceField) {
                $seenFieldNames[] = $sourceField['field_name'];
                $classification = $this->fieldClassifier->classify($sourceField['field_name'], $sourceField['normalized_type']);

                DatasetField::query()->updateOrCreate(
                    [
                        'dataset_id' => $dataset->id,
                        'table_name' => $dataset->main_table,
                        'field_name' => $sourceField['field_name'],
                    ],
                    [
                        'field_alias' => $sourceField['field_name'],
                        'display_name' => $sourceField['field_comment'] ?: $sourceField['field_name'],
                        'source_type' => 'physical',
                        'normalized_type' => $sourceField['normalized_type'] ?: 'unknown',
                        'semantic_type' => $classification['semantic_type'],
                        'is_dimension' => $classification['is_dimension'],
                        'is_metric' => $classification['is_metric'],
                        'is_visible' => true,
                        'is_filterable' => true,
                        'default_aggregate' => $classification['default_aggregate'],
                        'expression' => null,
                        'sort_order' => (int) $sourceField['ordinal_position'],
                    ],
                );
            }

            $staleFieldsQuery = DatasetField::query()
                ->where('dataset_id', $dataset->id)
                ->where('table_name', $dataset->main_table);

            if ($seenFieldNames !== []) {
                $staleFieldsQuery->whereNotIn('field_name', $seenFieldNames);
            }

            $staleFieldsQuery->delete();
        });

        $this->datasetCacheService->forget($dataset);

        return [
            'fields' => count($sourceFields),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateField(Dataset $dataset, DatasetField $field, array $payload): DatasetField
    {
        if ($field->dataset_id !== $dataset->id) {
            abort(404);
        }

        $field->fill($payload);
        $field->save();
        $this->datasetCacheService->forget($dataset);

        return $field->refresh();
    }

    private function assertSafeTable(DataSource $dataSource, string $tableName): void
    {
        if (! IdentifierGuard::isSafe($tableName)) {
            throw ValidationException::withMessages([
                'main_table' => ['The table name is not allowed.'],
            ]);
        }

        $tables = collect($this->metadataService->tables($dataSource));

        if (! $tables->contains('table_name', $tableName)) {
            throw ValidationException::withMessages([
                'main_table' => ['The selected table does not exist in the data source.'],
            ]);
        }
    }
}
