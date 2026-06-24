<?php

namespace App\Modules\Semantic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DimensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dataset_id' => $this->dataset_id,
            'name' => $this->name,
            'code' => $this->code,
            'field_name' => $this->field_name,
            'dimension_type' => $this->dimension_type,
            'time_grain_options_json' => $this->time_grain_options_json,
            'description' => $this->description,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
