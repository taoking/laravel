<?php

namespace App\Modules\Metadata\Services;

use App\Models\User;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Metadata\Models\MetadataAsset;
use Illuminate\Auth\Access\AuthorizationException;

class MetadataAuthorizer
{
    public function __construct(private readonly DataPermissionService $dataPermissionService) {}

    public function assertCanView(?User $user): void
    {
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }
    }

    public function assertCanManage(?User $user): void
    {
        if (! $this->canManage($user)) {
            throw new AuthorizationException;
        }
    }

    public function assertCanViewAsset(MetadataAsset $asset, ?User $user): void
    {
        if (! $this->canViewAsset($asset, $user)) {
            throw new AuthorizationException;
        }
    }

    public function canManage(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $this->isAdmin($user)
            || $user->hasPermission('metadata.manage')
            || $user->hasPermission('governance.manage')
            || $user->hasPermission('datasets.manage');
    }

    public function isAdmin(?User $user): bool
    {
        return $user instanceof User
            && $user->roles()->where('code', 'admin')->exists();
    }

    public function canViewAsset(MetadataAsset $asset, ?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($this->canManage($user) || (int) $asset->owner_id === (int) $user->id) {
            return true;
        }

        if ($asset->dataset_id !== null) {
            return $this->dataPermissionService->canAccessResource($user, 'dataset', (int) $asset->dataset_id, 'view');
        }

        if ($asset->asset_type === 'dashboard') {
            return true;
        }

        if ($asset->data_source_id !== null) {
            return Dataset::query()
                ->where('data_source_id', $asset->data_source_id)
                ->get(['id'])
                ->contains(fn (Dataset $dataset): bool => $this->dataPermissionService->canAccessResource($user, 'dataset', (int) $dataset->id, 'view'));
        }

        return true;
    }

    public function applyAssetVisibility($query, ?User $user): void
    {
        if ($this->canManage($user)) {
            return;
        }

        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');

            return;
        }

        $visibleDatasetIds = Dataset::query()
            ->get(['id'])
            ->filter(fn (Dataset $dataset): bool => $this->dataPermissionService->canAccessResource($user, 'dataset', (int) $dataset->id, 'view'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $query->where(function ($query) use ($user, $visibleDatasetIds): void {
            $query->where('owner_id', $user->id)
                ->orWhereNull('dataset_id');

            if ($visibleDatasetIds !== []) {
                $query->orWhereIn('dataset_id', $visibleDatasetIds);
            }
        });
    }
}
