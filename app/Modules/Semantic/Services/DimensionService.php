<?php

namespace App\Modules\Semantic\Services;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Services\IdentifierGuard;
use App\Modules\Semantic\Models\Dimension;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DimensionService
{
    public function __construct(private readonly SemanticLayerAuthorizer $authorizer) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $pageSize, ?User $actor): LengthAwarePaginator
    {
        $query = Dimension::query()
            ->with('dataset');

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
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', (string) $status))
            ->latest('id')
            ->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $actor): Dimension
    {
        $this->authorizer->assertCanManageDataset(Dataset::query()->findOrFail($payload['dataset_id']), $actor);
        $this->validateDefinition($payload);

        return Dimension::query()->create([
            ...$payload,
            'status' => $payload['status'] ?? 'active',
            'created_by' => $actor?->id,
        ])->load('dataset');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Dimension $dimension, array $payload, ?User $actor): Dimension
    {
        $dimension->loadMissing('dataset');
        $this->authorizer->assertCanManageDataset($dimension->dataset, $actor);
        $payload = [
            ...$dimension->only([
                'dataset_id',
                'name',
                'code',
                'field_name',
                'dimension_type',
                'time_grain_options_json',
                'description',
                'status',
            ]),
            ...$payload,
        ];
        $this->authorizer->assertCanManageDataset(Dataset::query()->findOrFail($payload['dataset_id']), $actor);
        $this->validateDefinition($payload, $dimension);
        $dimension->fill($payload);
        $dimension->save();

        return $dimension->refresh()->load('dataset');
    }

    public function delete(Dimension $dimension, ?User $actor): void
    {
        $dimension->loadMissing('dataset');
        $this->authorizer->assertCanManageDataset($dimension->dataset, $actor);
        $dimension->delete();
    }

    /**
     * @return list<Dimension>
     */
    public function initFromFields(Dataset $dataset, ?User $actor): array
    {
        $this->authorizer->assertCanManageDataset($dataset, $actor);
        $dataset->loadMissing('fields');
        $created = [];

        DB::transaction(function () use ($dataset, $actor, &$created): void {
            foreach ($dataset->fields as $field) {
                if (! $this->isDimensionCandidate($field->normalized_type, $field->semantic_type) || ! IdentifierGuard::isSafe($field->field_name)) {
                    continue;
                }

                $code = $field->field_alias ?: $field->field_name;

                if (! IdentifierGuard::isSafe($code) || Dimension::query()->where('dataset_id', $dataset->id)->where('code', $code)->exists()) {
                    continue;
                }

                $created[] = $this->create([
                    'dataset_id' => $dataset->id,
                    'name' => $field->display_name,
                    'code' => $code,
                    'field_name' => $field->field_name,
                    'dimension_type' => $this->dimensionType($field->normalized_type, $field->semantic_type),
                    'time_grain_options_json' => in_array($field->normalized_type, ['date', 'datetime'], true)
                        ? ['year', 'quarter', 'month', 'week', 'day', 'hour']
                        : null,
                    'status' => 'active',
                ], $actor);
            }
        });

        return $created;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateDefinition(array $payload, ?Dimension $dimension = null): void
    {
        if (! IdentifierGuard::isSafe((string) $payload['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Dimension code may only contain letters, numbers, and underscores.'],
            ]);
        }

        if (! IdentifierGuard::isSafe((string) $payload['field_name'])) {
            throw ValidationException::withMessages([
                'field_name' => ['Dimension field name is not allowed.'],
            ]);
        }

        if (! in_array($payload['dimension_type'], Dimension::TYPES, true)) {
            throw ValidationException::withMessages([
                'dimension_type' => ['The selected dimension type is not supported.'],
            ]);
        }

        if (! in_array($payload['status'] ?? 'active', Dimension::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['The selected dimension status is not supported.'],
            ]);
        }

        $dataset = Dataset::query()->with('fields')->findOrFail($payload['dataset_id']);

        if (! $dataset->fields->contains('field_name', $payload['field_name'])) {
            throw ValidationException::withMessages([
                'field_name' => ['The field must exist in dataset fields.'],
            ]);
        }

        $duplicate = Dimension::query()
            ->where('dataset_id', $dataset->id)
            ->where('code', $payload['code'])
            ->when($dimension !== null, fn ($query) => $query->whereKeyNot($dimension->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => ['Dimension code already exists in this dataset.'],
            ]);
        }
    }

    private function isDimensionCandidate(string $normalizedType, string $semanticType): bool
    {
        return in_array($normalizedType, ['string', 'date', 'datetime', 'enum'], true)
            || in_array($semanticType, ['region', 'organization', 'user', 'province', 'city', 'time'], true);
    }

    private function dimensionType(string $normalizedType, string $semanticType): string
    {
        if (in_array($normalizedType, ['date', 'datetime'], true)) {
            return $normalizedType;
        }

        return match ($semanticType) {
            'region', 'province', 'city' => 'region',
            'organization' => 'organization',
            'user' => 'user',
            default => $normalizedType === 'integer' ? 'number' : 'string',
        };
    }
}
