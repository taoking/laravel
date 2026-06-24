<?php

namespace App\Modules\Semantic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricDependencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'metric_id' => $this->metric_id,
            'depends_on_metric_id' => $this->depends_on_metric_id,
            'depends_on_metric_code' => $this->whenLoaded('dependsOnMetric', fn () => $this->dependsOnMetric?->code),
            'depends_on_field_name' => $this->depends_on_field_name,
            'dependency_type' => $this->dependency_type,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
