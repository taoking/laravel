<?php

namespace App\Modules\Acceleration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccelerationRecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dataset_id' => $this->dataset_id,
            'chart_id' => $this->chart_id,
            'dashboard_id' => $this->dashboard_id,
            'recommendation_type' => $this->recommendation_type,
            'status' => $this->status,
            'priority' => $this->priority,
            'reason' => $this->reason,
            'dimensions_json' => $this->dimensions_json,
            'metrics_json' => $this->metrics_json,
            'filters_json' => $this->filters_json,
            'time_field' => $this->time_field,
            'time_grain' => $this->time_grain,
            'estimated_query_count' => $this->estimated_query_count,
            'estimated_avg_duration_ms' => $this->estimated_avg_duration_ms,
            'estimated_max_duration_ms' => $this->estimated_max_duration_ms,
            'estimated_total_duration_ms' => $this->estimated_total_duration_ms,
            'estimated_benefit_score' => $this->estimated_benefit_score,
            'source_query_log_ids_json' => $this->source_query_log_ids_json,
            'created_profile_id' => $this->created_profile_id,
            'created_aggregate_definition_id' => $this->created_aggregate_definition_id,
            'accepted_by' => $this->accepted_by,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_aggregate_definition' => new AggregateDefinitionResource($this->whenLoaded('createdAggregateDefinition')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
