<?php

namespace App\Modules\Semantic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'dataset_id' => $this->dataset_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'metric_type' => $this->metric_type,
            'aggregate_function' => $this->aggregate_function,
            'source_field' => $this->source_field,
            'formula' => $this->formula,
            'unit' => $this->unit,
            'precision' => $this->precision,
            'format_type' => $this->format_type,
            'status' => $this->status,
            'version' => $this->version,
            'owner_id' => $this->owner_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'category' => $this->whenLoaded('category', fn () => (new MetricCategoryResource($this->category))->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
