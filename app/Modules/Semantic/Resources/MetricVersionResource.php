<?php

namespace App\Modules\Semantic\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'metric_id' => $this->metric_id,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'metric_type' => $this->metric_type,
            'aggregate_function' => $this->aggregate_function,
            'source_field' => $this->source_field,
            'formula' => $this->formula,
            'unit' => $this->unit,
            'precision' => $this->precision,
            'format_type' => $this->format_type,
            'status' => $this->status,
            'change_summary' => $this->change_summary,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
