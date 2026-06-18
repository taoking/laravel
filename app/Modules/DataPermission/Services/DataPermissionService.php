<?php

namespace App\Modules\DataPermission\Services;

use App\Models\User;
use App\Modules\Cache\Services\DatasetCacheService;
use App\Modules\DataPermission\Models\ColumnPermissionRule;
use App\Modules\DataPermission\Models\DataPermissionRule;
use App\Modules\DataPermission\Models\ResourcePermission;
use App\Modules\Dataset\Models\Dataset;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DataPermissionService
{
    public function __construct(
        private readonly DataPermissionSubjectResolver $subjectResolver,
        private readonly DatasetCacheService $datasetCacheService,
    ) {}

    public function paginateResourcePermissions(int $pageSize): LengthAwarePaginator
    {
        return ResourcePermission::query()->latest('id')->paginate($pageSize);
    }

    public function paginateDataRules(int $pageSize): LengthAwarePaginator
    {
        return DataPermissionRule::query()->with('dataset')->latest('id')->paginate($pageSize);
    }

    public function paginateColumnRules(int $pageSize): LengthAwarePaginator
    {
        return ColumnPermissionRule::query()->with('dataset')->latest('id')->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createResourcePermission(array $payload): ResourcePermission
    {
        return ResourcePermission::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateResourcePermission(ResourcePermission $permission, array $payload): ResourcePermission
    {
        $permission->fill($payload);
        $permission->save();

        return $permission->refresh();
    }

    public function deleteResourcePermission(ResourcePermission $permission): void
    {
        $permission->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDataRule(array $payload): DataPermissionRule
    {
        $payload['value_json'] = $this->normalizeValueJson($payload['value_json'] ?? null);
        $payload['value_type'] ??= 'static';
        $payload['status'] ??= 'active';

        $rule = DataPermissionRule::query()->create($payload);
        $this->datasetCacheService->forget((int) $rule->dataset_id);

        return $rule->load('dataset');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateDataRule(DataPermissionRule $rule, array $payload): DataPermissionRule
    {
        if (array_key_exists('value_json', $payload)) {
            $payload['value_json'] = $this->normalizeValueJson($payload['value_json']);
        }

        $oldDatasetId = (int) $rule->dataset_id;
        $rule->fill($payload);
        $rule->save();

        $this->datasetCacheService->forget($oldDatasetId);
        $this->datasetCacheService->forget((int) $rule->dataset_id);

        return $rule->refresh()->load('dataset');
    }

    public function deleteDataRule(DataPermissionRule $rule): void
    {
        $datasetId = (int) $rule->dataset_id;
        $rule->delete();
        $this->datasetCacheService->forget($datasetId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createColumnRule(array $payload): ColumnPermissionRule
    {
        $rule = ColumnPermissionRule::query()->create($payload);
        $this->datasetCacheService->forget((int) $rule->dataset_id);

        return $rule->load('dataset');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateColumnRule(ColumnPermissionRule $rule, array $payload): ColumnPermissionRule
    {
        $oldDatasetId = (int) $rule->dataset_id;
        $rule->fill($payload);
        $rule->save();

        $this->datasetCacheService->forget($oldDatasetId);
        $this->datasetCacheService->forget((int) $rule->dataset_id);

        return $rule->refresh()->load('dataset');
    }

    public function deleteColumnRule(ColumnPermissionRule $rule): void
    {
        $datasetId = (int) $rule->dataset_id;
        $rule->delete();
        $this->datasetCacheService->forget($datasetId);
    }

    public function assertCanAccessDataset(Dataset $dataset, ?User $user): void
    {
        if (! $this->canAccessResource($user, 'dataset', (int) $dataset->id, 'view')) {
            throw new AuthorizationException;
        }
    }

    public function canAccessResource(?User $user, string $resourceType, int $resourceId, string $permissionType = 'view'): bool
    {
        $baseQuery = ResourcePermission::query()
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId);

        if (! (clone $baseQuery)->exists()) {
            return true;
        }

        $subjects = $this->subjectResolver->subjects($user);

        if ($subjects === []) {
            return false;
        }

        return (clone $baseQuery)
            ->whereIn('permission_type', $this->grants($permissionType))
            ->where(fn (Builder $query) => $this->applySubjectScope($query, $subjects))
            ->exists();
    }

    /**
     * @return list<DataPermissionRule>
     */
    public function rowRules(Dataset $dataset, ?User $user): array
    {
        $subjects = $this->subjectResolver->subjects($user);

        if ($subjects === []) {
            return [];
        }

        return DataPermissionRule::query()
            ->where('dataset_id', $dataset->id)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $this->applySubjectScope($query, $subjects))
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function hiddenFields(Dataset $dataset, ?User $user): array
    {
        $subjects = $this->subjectResolver->subjects($user);

        if ($subjects === []) {
            return [];
        }

        return ColumnPermissionRule::query()
            ->where('dataset_id', $dataset->id)
            ->whereIn('permission_type', ['hidden', 'masked'])
            ->where(fn (Builder $query) => $this->applySubjectScope($query, $subjects))
            ->pluck('field_name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{subject_type: string, subject_id: int}>  $subjects
     */
    private function applySubjectScope(Builder $query, array $subjects): void
    {
        foreach ($subjects as $subject) {
            $query->orWhere(function (Builder $query) use ($subject): void {
                $query->where('subject_type', $subject['subject_type'])
                    ->where('subject_id', $subject['subject_id']);
            });
        }
    }

    /**
     * @return list<string>
     */
    private function grants(string $permissionType): array
    {
        return match ($permissionType) {
            'view' => ['view', 'edit', 'manage'],
            'edit' => ['edit', 'manage'],
            'delete' => ['delete', 'manage'],
            default => ['manage'],
        };
    }

    private function normalizeValueJson(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return ['value' => $value];
    }
}
