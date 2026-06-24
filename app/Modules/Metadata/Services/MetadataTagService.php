<?php

namespace App\Modules\Metadata\Services;

use App\Models\User;
use App\Modules\Metadata\Models\MetadataAssetTag;
use App\Modules\Metadata\Models\MetadataTag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class MetadataTagService
{
    public function __construct(
        private readonly MetadataAssetService $assetService,
        private readonly MetadataAuthorizer $authorizer,
    ) {}

    public function paginate(int $pageSize): LengthAwarePaginator
    {
        return MetadataTag::query()->orderBy('name')->paginate($pageSize);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, ?User $user): MetadataTag
    {
        $this->assertCanMaintainTag((string) $payload['name'], $user);

        return MetadataTag::query()->create(Arr::only($payload, ['name', 'color', 'description']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(MetadataTag $tag, array $payload, ?User $user): MetadataTag
    {
        $this->assertCanMaintainTag((string) ($payload['name'] ?? $tag->name), $user);
        $this->assertCanMaintainTag($tag->name, $user);
        $tag->fill(Arr::only($payload, ['name', 'color', 'description']));
        $tag->save();

        return $tag->refresh();
    }

    public function delete(MetadataTag $tag, ?User $user): void
    {
        $this->assertCanMaintainTag($tag->name, $user);
        $tag->delete();
    }

    public function attach(string $assetType, int $assetId, int|string $tag, ?User $user): MetadataAssetTag
    {
        $this->authorizer->assertCanManage($user);
        $asset = $this->assetService->find($assetType, $assetId, $user);
        $tag = $this->resolveTag($tag);
        $this->assertCanMaintainTag($tag->name, $user);

        MetadataAssetTag::query()->updateOrCreate([
            'asset_type' => $asset->asset_type,
            'asset_id' => $asset->asset_id,
            'tag_id' => $tag->id,
        ]);

        return MetadataAssetTag::query()
            ->where('asset_type', $asset->asset_type)
            ->where('asset_id', $asset->asset_id)
            ->where('tag_id', $tag->id)
            ->firstOrFail();
    }

    public function detach(string $assetType, int $assetId, int|string $tag, ?User $user): void
    {
        $this->authorizer->assertCanManage($user);
        $asset = $this->assetService->find($assetType, $assetId, $user);
        $tag = $this->resolveTag($tag);
        $this->assertCanMaintainTag($tag->name, $user);

        MetadataAssetTag::query()
            ->where('asset_type', $asset->asset_type)
            ->where('asset_id', $asset->asset_id)
            ->where('tag_id', $tag->id)
            ->delete();
    }

    /**
     * @return array<int, MetadataTag>
     */
    public function assetTags(string $assetType, int $assetId, ?User $user): array
    {
        $asset = $this->assetService->find($assetType, $assetId, $user);

        return MetadataAssetTag::query()
            ->with('tag')
            ->where('asset_type', $asset->asset_type)
            ->where('asset_id', $asset->asset_id)
            ->get()
            ->pluck('tag')
            ->filter()
            ->values()
            ->all();
    }

    public function resolveTag(int|string $tag): MetadataTag
    {
        return is_numeric($tag)
            ? MetadataTag::query()->findOrFail((int) $tag)
            : MetadataTag::query()->where('name', (string) $tag)->firstOrFail();
    }

    private function assertCanMaintainTag(string $name, ?User $user): void
    {
        $this->authorizer->assertCanManage($user);

        if ($this->isSensitiveTag($name) && ! $this->authorizer->isAdmin($user)) {
            throw new AuthorizationException;
        }
    }

    private function isSensitiveTag(string $name): bool
    {
        $name = strtolower($name);

        return str_contains($name, '敏感')
            || str_contains($name, 'sensitive')
            || str_contains($name, 'phone')
            || str_contains($name, 'id_card');
    }
}
