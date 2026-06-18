<?php

namespace App\Modules\Dashboard\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
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
            'layout_json' => $this->layout_json,
            'global_filters_json' => $this->global_filters_json,
            'status' => $this->status,
            'widgets_count' => $this->whenCounted('widgets'),
            'widgets' => DashboardWidgetResource::collection($this->whenLoaded('widgets')),
            'filters' => DashboardFilterResource::collection($this->whenLoaded('filters')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
