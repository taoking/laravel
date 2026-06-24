<?php

namespace App\Modules\Metadata\Services;

use App\Models\User;
use App\Modules\Metadata\Models\MetadataAsset;
use Illuminate\Database\Eloquent\Collection;

class MetadataSearchService
{
    public function __construct(
        private readonly MetadataAssetService $assetService,
        private readonly MetadataAuthorizer $authorizer,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MetadataAsset>
     */
    public function search(array $filters, ?User $user): Collection
    {
        $limit = min(max((int) ($filters['limit'] ?? 20), 1), 100);
        $assetType = $filters['asset_type'] ?? null;

        if (is_string($assetType) && $assetType !== '') {
            $this->assetService->assertAssetType($assetType);
        }

        $query = MetadataAsset::query()
            ->when($assetType, fn ($query, $type) => $query->where('asset_type', (string) $type))
            ->when($filters['keyword'] ?? null, function ($query, $keyword): void {
                $keyword = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $keyword).'%';
                $query->where(function ($query) use ($keyword): void {
                    $query->where('name', 'like', $keyword)
                        ->orWhere('code', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword);
                });
            })
            ->when($filters['tag'] ?? null, function ($query, $tag): void {
                $query->whereExists(function ($subQuery) use ($tag): void {
                    $subQuery->from('metadata_asset_tags')
                        ->join('metadata_tags', 'metadata_tags.id', '=', 'metadata_asset_tags.tag_id')
                        ->whereColumn('metadata_asset_tags.asset_type', 'metadata_assets.asset_type')
                        ->whereColumn('metadata_asset_tags.asset_id', 'metadata_assets.asset_id')
                        ->where('metadata_tags.name', (string) $tag);
                });
            });

        $this->authorizer->applyAssetVisibility($query, $user);

        return $query->orderBy('asset_type')->orderBy('name')->limit($limit)->get();
    }
}
