<?php

namespace App\Modules\Semantic\Services;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Services\IdentifierGuard;
use App\Modules\Semantic\Models\Metric;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetricService
{
    public function __construct(
        private readonly MetricFormulaParser $formulaParser,
        private readonly MetricVersionService $versionService,
        private readonly MetricDependencyService $dependencyService,
        private readonly SemanticLayerAuthorizer $authorizer,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $pageSize, ?User $actor): LengthAwarePaginator
    {
        $query = Metric::query()
            ->with(['category', 'dataset']);

        if ($datasetId = $filters['dataset_id'] ?? null) {
            $dataset = Dataset::query()->findOrFail((int) $datasetId);
            $this->authorizer->assertCanViewDataset($dataset, $actor);
            $query->where('dataset_id', (int) $datasetId);

            if (! $this->authorizer->canManageDataset($dataset, $actor)) {
                $query->where('status', 'active');
            }
        } elseif (! $this->authorizer->canManageAll($actor)) {
            $manageableDatasetIds = $this->authorizer->manageableDatasetIds($actor);
            $visibleOnlyDatasetIds = array_values(array_diff(
                $this->authorizer->visibleDatasetIds($actor),
                $manageableDatasetIds,
            ));

            $query->where(function ($query) use ($manageableDatasetIds, $visibleOnlyDatasetIds): void {
                if ($manageableDatasetIds !== []) {
                    $query->orWhereIn('dataset_id', $manageableDatasetIds);
                }

                if ($visibleOnlyDatasetIds !== []) {
                    $query->orWhere(function ($query) use ($visibleOnlyDatasetIds): void {
                        $query->whereIn('dataset_id', $visibleOnlyDatasetIds)
                            ->where('status', 'active');
                    });
                }
            });
        }

        return $query
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', (int) $categoryId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', (string) $status))
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): Metric
    {
        return DB::transaction(function () use ($payload, $actor): Metric {
            $payload = $this->normalize($payload);
            $this->authorizer->assertCanManageDataset(Dataset::query()->findOrFail($payload['dataset_id']), $actor);
            $this->validateDefinition($payload);

            $metric = Metric::query()->create([
                ...$payload,
                'version' => 1,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->dependencyService->sync($metric);
            $this->versionService->snapshot($metric, $actor, 'Initial metric definition.');

            return $metric->refresh()->load(['category', 'dataset', 'dependencies.dependsOnMetric']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Metric $metric, array $payload, ?User $actor): Metric
    {
        return DB::transaction(function () use ($metric, $payload, $actor): Metric {
            $this->authorizer->assertCanManageMetric($metric, $actor);
            $payload = $this->normalize([
                ...$metric->only([
                    'category_id',
                    'dataset_id',
                    'name',
                    'code',
                    'description',
                    'metric_type',
                    'aggregate_function',
                    'source_field',
                    'formula',
                    'unit',
                    'precision',
                    'format_type',
                    'status',
                    'owner_id',
                ]),
                ...$payload,
            ]);
            $this->authorizer->assertCanManageDataset(Dataset::query()->findOrFail($payload['dataset_id']), $actor);
            $this->validateDefinition($payload, $metric);
            $hasVersionedChanges = $this->versionService->hasVersionedChanges($metric, $payload);

            $metric->fill([
                ...$payload,
                'version' => $hasVersionedChanges ? $metric->version + 1 : $metric->version,
                'updated_by' => $actor?->id,
            ]);
            $metric->save();

            $this->dependencyService->sync($metric);

            if ($hasVersionedChanges) {
                $this->versionService->snapshot($metric, $actor, 'Metric definition updated.');
            }

            return $metric->refresh()->load(['category', 'dataset', 'dependencies.dependsOnMetric']);
        });
    }

    public function transition(Metric $metric, string $status, ?User $actor): Metric
    {
        if (! in_array($status, Metric::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['The selected metric status is not supported.'],
            ]);
        }

        return $this->update($metric, ['status' => $status], $actor);
    }

    public function delete(Metric $metric, ?User $actor): void
    {
        $this->authorizer->assertCanManageMetric($metric, $actor);

        if ($metric->usages()->exists()) {
            throw ValidationException::withMessages([
                'metric' => ['The metric is still used by charts or dashboards.'],
            ]);
        }

        if ($metric->dependents()->exists()) {
            throw ValidationException::withMessages([
                'metric' => ['The metric is still referenced by other metrics.'],
            ]);
        }

        $metric->delete();
    }

    /**
     * @return list<Metric>
     */
    public function initFromFields(Dataset $dataset, ?User $actor): array
    {
        $this->authorizer->assertCanManageDataset($dataset, $actor);
        $dataset->loadMissing('fields');
        $created = [];

        foreach ($dataset->fields as $field) {
            $aggregate = $this->defaultAggregateForField($field->field_name, $field->normalized_type);

            if ($aggregate === null || ! IdentifierGuard::isSafe($field->field_name)) {
                continue;
            }

            $code = $field->field_name.'_'.$aggregate;

            if (Metric::query()->where('code', $code)->exists()) {
                continue;
            }

            $created[] = $this->create([
                'dataset_id' => $dataset->id,
                'name' => $field->display_name.' '.strtoupper($aggregate),
                'code' => $code,
                'description' => 'Initialized from dataset field '.$field->field_name.'.',
                'metric_type' => 'base',
                'aggregate_function' => $aggregate,
                'source_field' => $field->field_name,
                'status' => 'draft',
            ], $actor);
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $payload['metric_type'] ??= 'base';
        $payload['aggregate_function'] ??= $payload['metric_type'] === 'base' ? 'sum' : 'expression';
        $payload['status'] ??= 'draft';
        $payload['precision'] ??= 2;
        $payload['format_type'] ??= 'number';
        $payload['source_field'] = $payload['source_field'] ?? null;
        $payload['formula'] = $payload['formula'] ?? null;
        $payload['category_id'] = $payload['category_id'] ?? null;
        $payload['owner_id'] = $payload['owner_id'] ?? null;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateDefinition(array $payload, ?Metric $metric = null): void
    {
        if (! IdentifierGuard::isSafe((string) $payload['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Metric code may only contain letters, numbers, and underscores.'],
            ]);
        }

        if (! in_array($payload['metric_type'], Metric::TYPES, true)) {
            throw ValidationException::withMessages([
                'metric_type' => ['The selected metric type is not supported.'],
            ]);
        }

        if (! in_array($payload['aggregate_function'], Metric::AGGREGATES, true)) {
            throw ValidationException::withMessages([
                'aggregate_function' => ['The selected aggregate function is not supported.'],
            ]);
        }

        if (! in_array($payload['status'], Metric::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['The selected metric status is not supported.'],
            ]);
        }

        $dataset = Dataset::query()->with('fields')->findOrFail($payload['dataset_id']);

        if ($payload['metric_type'] === 'base') {
            $sourceField = (string) ($payload['source_field'] ?? '');

            if (! $dataset->fields->contains('field_name', $sourceField)) {
                throw ValidationException::withMessages([
                    'source_field' => ['The source field must exist in dataset fields.'],
                ]);
            }

            return;
        }

        if (! is_string($payload['formula']) || trim($payload['formula']) === '') {
            throw ValidationException::withMessages([
                'formula' => ['Formula is required for derived and compound metrics.'],
            ]);
        }

        $allowedCodes = Metric::query()
            ->where('dataset_id', $dataset->id)
            ->where('status', 'active')
            ->when($metric !== null, fn ($query) => $query->whereKeyNot($metric->id))
            ->pluck('code')
            ->all();

        try {
            $this->formulaParser->validate($payload['formula'], $allowedCodes);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'formula' => [$exception->getMessage()],
            ]);
        }
    }

    private function defaultAggregateForField(string $fieldName, string $normalizedType): ?string
    {
        if (strtolower($fieldName) === 'id' || str_ends_with(strtolower($fieldName), '_id')) {
            return 'count';
        }

        return in_array($normalizedType, ['integer', 'decimal', 'float', 'number'], true) ? 'sum' : null;
    }
}
