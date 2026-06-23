<?php

namespace App\Modules\Acceleration\Services;

use App\Models\User;
use App\Modules\Acceleration\Jobs\BuildAggregateTableJob;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Models\AccelerationTask;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AggregateDefinitionService
{
    public function __construct(private readonly AggregateSchemaService $schemaService) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return AccelerationAggregateDefinition::query()
            ->with(['dataset', 'detailProfile', 'aggregateProfile', 'columns'])
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AccelerationAggregateDefinition>
     */
    public function forDataset(Dataset $dataset)
    {
        return AccelerationAggregateDefinition::query()
            ->with(['detailProfile', 'aggregateProfile', 'columns'])
            ->where('dataset_id', $dataset->id)
            ->latest('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor = null, ?Dataset $scopedDataset = null): AccelerationAggregateDefinition
    {
        $dataset = $scopedDataset ?? $this->datasetFromPayload($payload);
        $dataset->loadMissing('fields');
        $detailProfile = $this->resolveDetailProfile($dataset, $payload['detail_profile_id'] ?? null);
        $dimensions = $this->normalizeDimensions($payload['dimensions'] ?? []);
        $metrics = $this->normalizeMetrics($payload['metrics'] ?? []);
        $filters = $this->normalizeFilters($payload['filters'] ?? []);

        $this->assertLimits($dimensions, $metrics);
        $this->assertDatasetFields($dataset, $dimensions, $metrics, $payload['time_field'] ?? null, $filters);
        $this->assertTargetIdentifier($payload['target_database'] ?? null, 'target_database');
        $this->assertTargetIdentifier($payload['target_table'] ?? null, 'target_table');

        return DB::transaction(function () use ($payload, $actor, $dataset, $detailProfile, $dimensions, $metrics, $filters): AccelerationAggregateDefinition {
            $definition = AccelerationAggregateDefinition::query()->create([
                'dataset_id' => $dataset->id,
                'detail_profile_id' => $detailProfile->id,
                'name' => $payload['name'] ?? $dataset->name.' Aggregate',
                'status' => $payload['status'] ?? 'disabled',
                'target_database' => $payload['target_database'] ?? $detailProfile->target_database ?? config('bi_acceleration.clickhouse.database'),
                'target_table' => $payload['target_table'] ?? null,
                'time_field' => $payload['time_field'] ?? null,
                'time_grain' => $payload['time_grain'] ?? 'none',
                'dimensions_json' => $dimensions,
                'metrics_json' => $metrics,
                'filters_json' => $filters,
                'refresh_type' => $payload['refresh_type'] ?? 'manual',
                'created_by' => $actor?->id,
            ]);

            if ($definition->target_table === null || $definition->target_table === '') {
                $definition->forceFill(['target_table' => $this->defaultTargetTable($dataset, $definition)])->save();
            }

            $profile = AccelerationProfile::query()->create([
                'dataset_id' => $dataset->id,
                'name' => $definition->name.' Profile',
                'engine_type' => 'clickhouse',
                'mode' => 'aggregate_table',
                'status' => $definition->status,
                'source_connection_id' => $detailProfile->source_connection_id,
                'target_connection_id' => $detailProfile->target_connection_id,
                'target_database' => $definition->target_database,
                'target_table' => $definition->target_table,
                'refresh_type' => $definition->refresh_type,
                'config_json' => $this->profileConfig($definition, $detailProfile),
            ]);

            $definition->forceFill(['aggregate_profile_id' => $profile->id])->save();
            $this->schemaService->syncColumns($definition, $payload['columns'] ?? []);

            return $definition->refresh()->load(['dataset', 'detailProfile', 'aggregateProfile', 'columns']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(AccelerationAggregateDefinition $definition, array $payload): AccelerationAggregateDefinition
    {
        $definition->loadMissing('dataset.fields', 'detailProfile', 'aggregateProfile');
        $dataset = $definition->dataset;
        $detailProfile = array_key_exists('detail_profile_id', $payload)
            ? $this->resolveDetailProfile($dataset, $payload['detail_profile_id'])
            : $definition->detailProfile;
        $dimensions = array_key_exists('dimensions', $payload) ? $this->normalizeDimensions($payload['dimensions'] ?? []) : ($definition->dimensions_json ?? []);
        $metrics = array_key_exists('metrics', $payload) ? $this->normalizeMetrics($payload['metrics'] ?? []) : ($definition->metrics_json ?? []);
        $filters = array_key_exists('filters', $payload) ? $this->normalizeFilters($payload['filters'] ?? []) : ($definition->filters_json ?? []);
        $timeField = array_key_exists('time_field', $payload) ? ($payload['time_field'] ?? null) : $definition->time_field;

        $this->assertLimits($dimensions, $metrics);
        $this->assertDatasetFields($dataset, $dimensions, $metrics, $timeField, $filters);
        $this->assertTargetIdentifier($payload['target_database'] ?? null, 'target_database');
        $this->assertTargetIdentifier($payload['target_table'] ?? null, 'target_table');

        return DB::transaction(function () use ($definition, $payload, $detailProfile, $dimensions, $metrics, $filters, $timeField): AccelerationAggregateDefinition {
            $definition->fill([
                ...collect($payload)->only([
                    'name',
                    'status',
                    'target_database',
                    'target_table',
                    'time_grain',
                    'refresh_type',
                ])->all(),
                'detail_profile_id' => $detailProfile->id,
                'time_field' => $timeField,
                'dimensions_json' => $dimensions,
                'metrics_json' => $metrics,
                'filters_json' => $filters,
                'version' => $definition->version + 1,
            ]);
            $definition->save();

            $profile = $definition->aggregateProfile;

            if ($profile instanceof AccelerationProfile) {
                $profile->forceFill([
                    'name' => $definition->name.' Profile',
                    'status' => $definition->status,
                    'source_connection_id' => $detailProfile->source_connection_id,
                    'target_connection_id' => $detailProfile->target_connection_id,
                    'target_database' => $definition->target_database,
                    'target_table' => $definition->target_table,
                    'refresh_type' => $definition->refresh_type,
                    'version' => $profile->version + 1,
                    'config_json' => $this->profileConfig($definition, $detailProfile),
                ])->save();
            }

            if (array_key_exists('columns', $payload) || array_key_exists('dimensions', $payload) || array_key_exists('metrics', $payload) || array_key_exists('time_field', $payload) || array_key_exists('time_grain', $payload)) {
                $this->schemaService->syncColumns($definition, $payload['columns'] ?? []);
            }

            return $definition->refresh()->load(['dataset', 'detailProfile', 'aggregateProfile', 'columns']);
        });
    }

    public function delete(AccelerationAggregateDefinition $definition): void
    {
        $definition->loadMissing('aggregateProfile');
        $profile = $definition->aggregateProfile;

        DB::transaction(function () use ($definition, $profile): void {
            $definition->delete();
            $profile?->delete();
        });
    }

    public function build(AccelerationAggregateDefinition $definition, ?User $actor = null, string $taskType = 'build_aggregate'): AccelerationTask
    {
        $definition->loadMissing('aggregateProfile');
        $profile = $definition->aggregateProfile;

        if (! $profile instanceof AccelerationProfile) {
            throw ValidationException::withMessages([
                'aggregate_profile_id' => ['Aggregate profile is missing.'],
            ]);
        }

        $definition->forceFill([
            'status' => 'building',
            'last_error_message' => null,
        ])->save();

        $profile->forceFill([
            'status' => 'building',
            'last_error_message' => null,
        ])->save();

        $task = $profile->tasks()->create([
            'task_type' => $taskType,
            'status' => 'pending',
            'created_by' => $actor?->id,
        ]);

        BuildAggregateTableJob::dispatch($task->id);

        return $task->refresh()->load('profile');
    }

    public function refresh(AccelerationAggregateDefinition $definition, ?User $actor = null): AccelerationTask
    {
        return $this->build($definition, $actor, 'refresh_aggregate');
    }

    public function activate(AccelerationAggregateDefinition $definition): AccelerationAggregateDefinition
    {
        return $this->setStatus($definition, 'active');
    }

    public function disable(AccelerationAggregateDefinition $definition): AccelerationAggregateDefinition
    {
        return $this->setStatus($definition, 'disabled');
    }

    private function setStatus(AccelerationAggregateDefinition $definition, string $status): AccelerationAggregateDefinition
    {
        $definition->loadMissing('aggregateProfile');
        $definition->forceFill([
            'status' => $status,
            'version' => $definition->version + 1,
        ])->save();

        if ($definition->aggregateProfile instanceof AccelerationProfile) {
            $definition->aggregateProfile->forceFill([
                'status' => $status,
                'version' => $definition->aggregateProfile->version + 1,
            ])->save();
        }

        return $definition->refresh()->load(['dataset', 'detailProfile', 'aggregateProfile', 'columns']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function datasetFromPayload(array $payload): Dataset
    {
        if (! isset($payload['dataset_id'])) {
            throw ValidationException::withMessages([
                'dataset_id' => ['A dataset id is required.'],
            ]);
        }

        return Dataset::query()->with('fields')->findOrFail($payload['dataset_id']);
    }

    private function resolveDetailProfile(Dataset $dataset, mixed $detailProfileId): AccelerationProfile
    {
        $query = AccelerationProfile::query()
            ->with('columns')
            ->where('dataset_id', $dataset->id)
            ->where('engine_type', 'clickhouse')
            ->where('mode', 'detail_table');

        $profile = $detailProfileId !== null
            ? (clone $query)->findOrFail($detailProfileId)
            : $query->where('status', 'active')->latest('id')->first();

        if (! $profile instanceof AccelerationProfile) {
            throw ValidationException::withMessages([
                'detail_profile_id' => ['No ClickHouse detail acceleration profile is available for this dataset.'],
            ]);
        }

        return $profile;
    }

    /**
     * @return list<string>
     */
    private function normalizeDimensions(mixed $dimensions): array
    {
        return collect(is_array($dimensions) ? $dimensions : [])
            ->map(fn (mixed $dimension): string => is_array($dimension) ? (string) ($dimension['field'] ?? '') : (string) $dimension)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{field: string, aggregate: string, alias?: string}>
     */
    private function normalizeMetrics(mixed $metrics): array
    {
        return collect(is_array($metrics) ? $metrics : [])
            ->map(fn (mixed $metric): array => is_array($metric) ? $metric : [])
            ->filter(fn (array $metric): bool => isset($metric['field'], $metric['aggregate']))
            ->map(fn (array $metric): array => [
                'field' => (string) $metric['field'],
                'aggregate' => (string) $metric['aggregate'],
                ...(isset($metric['alias']) && $metric['alias'] !== null ? ['alias' => (string) $metric['alias']] : []),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFilters(mixed $filters): array
    {
        return collect(is_array($filters) ? $filters : [])
            ->filter(fn (mixed $filter): bool => is_array($filter) && isset($filter['field'], $filter['operator']))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array{field: string, aggregate: string, alias?: string}>  $metrics
     */
    private function assertLimits(array $dimensions, array $metrics): void
    {
        if (count($dimensions) > (int) config('bi_acceleration.aggregate.max_dimensions', 5)) {
            throw ValidationException::withMessages([
                'dimensions' => ['Too many aggregate dimensions.'],
            ]);
        }

        if (count($metrics) > (int) config('bi_acceleration.aggregate.max_metrics', 10)) {
            throw ValidationException::withMessages([
                'metrics' => ['Too many aggregate metrics.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array{field: string, aggregate: string, alias?: string}>  $metrics
     * @param  list<array<string, mixed>>  $filters
     */
    private function assertDatasetFields(Dataset $dataset, array $dimensions, array $metrics, mixed $timeField, array $filters): void
    {
        /** @var Collection<string, DatasetField> $fieldsByName */
        $fieldsByName = $dataset->fields->keyBy('field_name');

        foreach ($dimensions as $dimension) {
            $field = $this->field($fieldsByName, $dimension, 'dimensions');

            if (! $field->is_dimension) {
                throw ValidationException::withMessages([
                    'dimensions' => ["Field [{$dimension}] is not a dimension."],
                ]);
            }
        }

        if ($timeField !== null && $timeField !== '') {
            $this->field($fieldsByName, (string) $timeField, 'time_field');
        }

        foreach ($metrics as $metric) {
            $field = $this->field($fieldsByName, $metric['field'], 'metrics');

            if (! $field->is_metric && ! in_array($metric['aggregate'], ['count', 'countDistinct'], true)) {
                throw ValidationException::withMessages([
                    'metrics' => ["Field [{$metric['field']}] is not a metric."],
                ]);
            }

            if (isset($metric['alias'])) {
                $this->assertTargetIdentifier($metric['alias'], 'metrics');
            }
        }

        foreach ($filters as $filter) {
            $this->field($fieldsByName, (string) $filter['field'], 'filters');
        }
    }

    /**
     * @param  Collection<string, DatasetField>  $fieldsByName
     */
    private function field($fieldsByName, string $fieldName, string $key): DatasetField
    {
        $field = $fieldsByName->get($fieldName);

        if (! $field instanceof DatasetField) {
            throw ValidationException::withMessages([
                $key => ["Field [{$fieldName}] does not belong to the dataset."],
            ]);
        }

        if (! IdentifierGuard::isSafe($fieldName)) {
            throw ValidationException::withMessages([
                $key => ["Identifier [{$fieldName}] is not allowed."],
            ]);
        }

        return $field;
    }

    private function assertTargetIdentifier(mixed $identifier, string $field): void
    {
        if ($identifier === null || $identifier === '') {
            return;
        }

        if (! IdentifierGuard::isSafe((string) $identifier)) {
            throw ValidationException::withMessages([
                $field => ["Identifier [{$identifier}] is not allowed."],
            ]);
        }
    }

    private function defaultTargetTable(Dataset $dataset, AccelerationAggregateDefinition $definition): string
    {
        return 'dataset_'.$dataset->id.'_agg_'.$definition->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileConfig(AccelerationAggregateDefinition $definition, AccelerationProfile $detailProfile): array
    {
        return [
            'aggregate_definition_id' => $definition->id,
            'source_detail_profile_id' => $detailProfile->id,
            'time_field' => $definition->time_field,
            'time_grain' => $definition->time_grain,
            'dimensions' => $definition->dimensions_json ?? [],
            'metrics' => $definition->metrics_json ?? [],
        ];
    }
}
