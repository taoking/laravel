<?php

namespace App\Modules\Semantic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricUsageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'metric_id' => $this->metric_id,
            'metric_version' => $this->metric_version,
            'used_by_type' => $this->used_by_type,
            'used_by_id' => $this->used_by_id,
            'usage_context' => $this->usage_context,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
