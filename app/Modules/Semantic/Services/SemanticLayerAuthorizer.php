<?php

namespace App\Modules\Semantic\Services;

use App\Models\User;
use App\Modules\DataPermission\Models\ResourcePermission;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Semantic\Models\Metric;
use Illuminate\Auth\Access\AuthorizationException;

class SemanticLayerAuthorizer
{
    public function __construct(private readonly DataPermissionService $dataPermissionService) {}

    public function assertCanViewDataset(Dataset $dataset, ?User $user): void
    {
        if (! $this->canViewDataset($dataset, $user)) {
            throw new AuthorizationException;
        }
    }

    public function assertCanManageDataset(Dataset $dataset, ?User $user): void
    {
        if (! $this->canManageDataset($dataset, $user)) {
            throw new AuthorizationException;
        }
    }

    public function assertCanViewMetric(Metric $metric, ?User $user): void
    {
        if (! $this->canViewMetric($metric, $user)) {
            throw new AuthorizationException;
        }
    }

    public function assertCanManageMetric(Metric $metric, ?User $user): void
    {
        $metric->loadMissing('dataset');
        $dataset = $metric->dataset;

        if (! $dataset instanceof Dataset || ! $this->canManageDataset($dataset, $user)) {
            throw new AuthorizationException;
        }
    }

    public function canViewMetric(Metric $metric, ?User $user): bool
    {
        $metric->loadMissing('dataset');
        $dataset = $metric->dataset;

        if (! $dataset instanceof Dataset) {
            return false;
        }

        if ($this->canManageDataset($dataset, $user)) {
            return true;
        }

        return $metric->status === 'active' && $this->canViewDataset($dataset, $user);
    }

    public function canManageDataset(Dataset $dataset, ?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($this->canManageAll($user) || (int) $dataset->created_by === (int) $user->id) {
            return true;
        }

        return $this->hasExplicitDatasetManageGrant($dataset, $user);
    }

    public function canViewDataset(Dataset $dataset, ?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($this->canManageDataset($dataset, $user)) {
            return true;
        }

        return $this->dataPermissionService->canAccessResource($user, 'dataset', (int) $dataset->id, 'view');
    }

    public function canManageAll(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->roles()->where('code', 'admin')->exists()
            || $user->hasPermission('semantic.manage')
            || $user->hasPermission('metrics.manage')
            || $user->hasPermission('datasets.manage');
    }

    private function hasExplicitDatasetManageGrant(Dataset $dataset, User $user): bool
    {
        $subjects = $this->subjects($user);

        if ($subjects === []) {
            return false;
        }

        return ResourcePermission::query()
            ->where('resource_type', 'dataset')
            ->where('resource_id', $dataset->id)
            ->whereIn('permission_type', ['edit', 'manage'])
            ->where(function ($query) use ($subjects): void {
                foreach ($subjects as $subject) {
                    $query->orWhere(function ($query) use ($subject): void {
                        $query->where('subject_type', $subject['subject_type'])
                            ->where('subject_id', $subject['subject_id']);
                    });
                }
            })
            ->exists();
    }

    /**
     * @return list<array{subject_type: string, subject_id: int}>
     */
    private function subjects(User $user): array
    {
        $subjects = [
            ['subject_type' => 'user', 'subject_id' => (int) $user->id],
        ];

        foreach ($user->roles()->pluck('roles.id') as $roleId) {
            $subjects[] = ['subject_type' => 'role', 'subject_id' => (int) $roleId];
        }

        if ($user->department_id !== null) {
            $subjects[] = ['subject_type' => 'department', 'subject_id' => (int) $user->department_id];
        }

        if ($user->organization_id !== null) {
            $subjects[] = ['subject_type' => 'organization', 'subject_id' => (int) $user->organization_id];
        }

        return $subjects;
    }

    /**
     * @return list<int>
     */
    public function manageableDatasetIds(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        if ($this->canManageAll($user)) {
            return Dataset::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return Dataset::query()
            ->get(['id', 'created_by'])
            ->filter(fn (Dataset $dataset): bool => $this->canManageDataset($dataset, $user))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function visibleDatasetIds(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        if ($this->canManageAll($user)) {
            return Dataset::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        return Dataset::query()
            ->get(['id', 'created_by'])
            ->filter(fn (Dataset $dataset): bool => $this->canViewDataset($dataset, $user))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
