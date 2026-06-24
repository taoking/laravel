<?php

namespace App\Modules\Metadata\Services;

use App\Modules\Metadata\Models\MetadataAsset;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MetadataLifecycleService
{
    public function __construct(private readonly MetadataSyncService $syncService) {}

    /**
     * @return array<string, mixed>|null
     */
    public function sync(string $assetType, int $assetId): ?array
    {
        try {
            return $this->syncService->syncAssetMetadata($assetType, $assetId);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    public function archive(string $assetType, int $assetId): void
    {
        MetadataAsset::query()
            ->where('asset_type', $assetType)
            ->where('asset_id', $assetId)
            ->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);
    }
}
