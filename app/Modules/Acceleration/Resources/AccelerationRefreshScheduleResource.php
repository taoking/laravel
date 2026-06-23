<?php

namespace App\Modules\Acceleration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccelerationRefreshScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'refresh_type' => $this->refresh_type,
            'cron_expression' => $this->cron_expression,
            'enabled' => $this->enabled,
            'last_run_at' => $this->last_run_at?->toISOString(),
            'next_run_at' => $this->next_run_at?->toISOString(),
            'last_task_id' => $this->last_task_id,
            'last_status' => $this->last_status,
            'last_error_message' => $this->last_error_message,
            'created_by' => $this->created_by,
            'last_task' => new AccelerationTaskResource($this->whenLoaded('lastTask')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
