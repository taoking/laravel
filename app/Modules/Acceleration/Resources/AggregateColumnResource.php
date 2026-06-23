<?php

namespace App\Modules\Acceleration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AggregateColumnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aggregate_definition_id' => $this->aggregate_definition_id,
            'source_field_name' => $this->source_field_name,
            'target_field_name' => $this->target_field_name,
            'column_role' => $this->column_role,
            'aggregate_function' => $this->aggregate_function,
            'source_type' => $this->source_type,
            'target_type' => $this->target_type,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
