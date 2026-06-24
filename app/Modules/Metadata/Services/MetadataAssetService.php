<?php

namespace App\Modules\Metadata\Services;

use App\Models\User;
use App\Modules\Metadata\Models\MetadataAsset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class MetadataAssetService
{
    public function __construct(private readonly MetadataAuthorizer $authorizer) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $pageSize, ?User $user): LengthAwarePaginator
    {
        $query = MetadataAsset::query()
            ->when($filters['asset_type'] ?? null, fn ($query, $type) => $query->where('asset_type', (string) $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', (string) $status))
            ->when($filters['owner_id'] ?? null, fn ($query, $ownerId) => $query->where('owner_id', (int) $ownerId))
            ->when($filters['data_source_id'] ?? null, fn ($query, $dataSourceId) => $query->where('data_source_id', (int) $dataSourceId))
            ->when($filters['dataset_id'] ?? null, fn ($query, $datasetId) => $query->where('dataset_id', (int) $datasetId))
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

        return $query->latest('updated_at')->paginate($pageSize);
    }

    public function find(string $assetType, int $assetId, ?User $user = null): MetadataAsset
    {
        $this->assertAssetType($assetType);
        $asset = MetadataAsset::query()
            ->where('asset_type', $assetType)
            ->where('asset_id', $assetId)
            ->firstOrFail();

        if ($user !== null) {
            $this->authorizer->assertCanViewAsset($asset, $user);
        }

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function register(string $assetType, int $assetId, array $payload): MetadataAsset
    {
        $this->assertAssetType($assetType);

        $properties = $this->sanitizeProperties($payload['properties_json'] ?? []);
        $payload = Arr::only($payload, [
            'name',
            'code',
            'description',
            'data_source_id',
            'dataset_id',
            'status',
            'owner_id',
            'tags_json',
        ]);
        $payload['properties_json'] = $properties;
        $payload['last_synced_at'] = now();

        return MetadataAsset::query()->updateOrCreate(
            [
                'asset_type' => $assetType,
                'asset_id' => $assetId,
            ],
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $assetType, int $assetId, array $payload, ?User $user): MetadataAsset
    {
        $this->authorizer->assertCanManage($user);
        $asset = $this->find($assetType, $assetId);
        $asset->fill(Arr::only($payload, ['description', 'owner_id', 'tags_json', 'status']));
        $asset->save();

        return $asset->refresh();
    }

    public function archive(string $assetType, int $assetId, ?User $user): MetadataAsset
    {
        return $this->update($assetType, $assetId, ['status' => 'archived'], $user);
    }

    public function assertAssetType(string $assetType): void
    {
        if (! in_array($assetType, MetadataAsset::TYPES, true)) {
            throw ValidationException::withMessages([
                'asset_type' => ['The selected metadata asset type is not supported.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function sanitizeProperties(array $properties): array
    {
        return collect($properties)
            ->reject(fn (mixed $value, string $key): bool => str_contains(strtolower($key), 'password')
                || str_contains(strtolower($key), 'secret')
                || str_contains(strtolower($key), 'token')
                || str_contains(strtolower($key), 'connection_string'))
            ->all();
    }
}
