<?php

namespace App\Modules\Acceleration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccelerationTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'acceleration_profile_id' => $this->acceleration_profile_id,
            'task_type' => $this->task_type,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'source_row_count' => $this->source_row_count,
            'target_row_count' => $this->target_row_count,
            'duration_ms' => $this->duration_ms,
            'error_message' => $this->error_message,
            'logs_json' => $this->logs_json,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
