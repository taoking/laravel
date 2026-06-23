<?php

namespace App\Modules\Acceleration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccelerationProfileResource extends JsonResource
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
            'engine_type' => $this->engine_type,
            'mode' => $this->mode,
            'status' => $this->status,
            'source_connection_id' => $this->source_connection_id,
            'target_connection_id' => $this->target_connection_id,
            'target_database' => $this->target_database,
            'target_table' => $this->target_table,
            'refresh_type' => $this->refresh_type,
            'refresh_interval_minutes' => $this->refresh_interval_minutes,
            'last_refresh_at' => $this->last_refresh_at?->toISOString(),
            'last_success_at' => $this->last_success_at?->toISOString(),
            'last_error_message' => $this->last_error_message,
            'row_count' => $this->row_count,
            'version' => $this->version,
            'config_json' => $this->config_json,
            'columns' => AccelerationColumnResource::collection($this->whenLoaded('columns')),
            'recent_tasks' => AccelerationTaskResource::collection($this->whenLoaded('tasks')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
