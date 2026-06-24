<?php

namespace App\Modules\Metadata\Resources;

use App\Modules\Metadata\Models\MetadataAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetadataUsageStatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $asset = MetadataAsset::query()
            ->where('asset_type', $this->asset_type)
            ->where('asset_id', $this->asset_id)
            ->first();

        return [
            'id' => $this->id,
            'asset_type' => $this->asset_type,
            'asset_id' => $this->asset_id,
            'asset' => $asset ? (new MetadataAssetResource($asset))->resolve($request) : null,
            'usage_date' => $this->usage_date?->toDateString(),
            'query_count' => $this->query_count,
            'view_count' => $this->view_count,
            'edit_count' => $this->edit_count,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'avg_duration_ms' => $this->avg_duration_ms,
            'slow_query_count' => $this->slow_query_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
