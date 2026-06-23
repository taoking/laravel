<?php

namespace App\Modules\Acceleration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AggregateDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dataset_id' => $this->dataset_id,
            'detail_profile_id' => $this->detail_profile_id,
            'aggregate_profile_id' => $this->aggregate_profile_id,
            'name' => $this->name,
            'status' => $this->status,
            'target_database' => $this->target_database,
            'target_table' => $this->target_table,
            'time_field' => $this->time_field,
            'time_grain' => $this->time_grain,
            'dimensions_json' => $this->dimensions_json,
            'metrics_json' => $this->metrics_json,
            'filters_json' => $this->filters_json,
            'refresh_type' => $this->refresh_type,
            'last_refresh_at' => $this->last_refresh_at?->toISOString(),
            'last_success_at' => $this->last_success_at?->toISOString(),
            'last_error_message' => $this->last_error_message,
            'row_count' => $this->row_count,
            'version' => $this->version,
            'created_by' => $this->created_by,
            'columns' => AggregateColumnResource::collection($this->whenLoaded('columns')),
            'aggregate_profile' => new AccelerationProfileResource($this->whenLoaded('aggregateProfile')),
            'detail_profile' => new AccelerationProfileResource($this->whenLoaded('detailProfile')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
