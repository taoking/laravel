<?php

namespace App\Modules\Chart\Resources;

use App\Modules\Dataset\Resources\DatasetResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'dataset_id' => $this->dataset_id,
            'dataset' => $this->whenLoaded(
                'dataset',
                fn () => (new DatasetResource($this->dataset))->resolve($request),
            ),
            'chart_type' => $this->chart_type,
            'config_json' => $this->config_json,
            'style_json' => $this->style_json,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
