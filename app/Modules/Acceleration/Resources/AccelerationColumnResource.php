<?php

namespace App\Modules\Acceleration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccelerationColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'acceleration_profile_id' => $this->acceleration_profile_id,
            'dataset_field_id' => $this->dataset_field_id,
            'source_field_name' => $this->source_field_name,
            'target_field_name' => $this->target_field_name,
            'source_type' => $this->source_type,
            'target_type' => $this->target_type,
            'is_dimension' => $this->is_dimension,
            'is_metric' => $this->is_metric,
            'aggregate_functions_json' => $this->aggregate_functions_json,
            'is_partition_key' => $this->is_partition_key,
            'is_order_key' => $this->is_order_key,
            'is_nullable' => $this->is_nullable,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
