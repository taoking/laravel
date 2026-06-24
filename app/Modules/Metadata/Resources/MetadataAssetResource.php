<?php

namespace App\Modules\Metadata\Resources;

use App\Modules\Metadata\Models\MetadataAssetTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetadataAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_type' => $this->asset_type,
            'asset_id' => $this->asset_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'data_source_id' => $this->data_source_id,
            'dataset_id' => $this->dataset_id,
            'status' => $this->status,
            'owner_id' => $this->owner_id,
            'tags_json' => $this->tags_json,
            'tags' => MetadataTagResource::collection($this->tags())->resolve($request),
            'properties_json' => $this->properties_json,
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function tags()
    {
        return MetadataAssetTag::query()
            ->with('tag')
            ->where('asset_type', $this->asset_type)
            ->where('asset_id', $this->asset_id)
            ->get()
            ->pluck('tag')
            ->filter()
            ->values();
    }
}
