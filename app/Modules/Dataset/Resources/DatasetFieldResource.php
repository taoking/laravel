<?php

namespace App\Modules\Dataset\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatasetFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dataset_id' => $this->dataset_id,
            'table_name' => $this->table_name,
            'field_name' => $this->field_name,
            'field_alias' => $this->field_alias,
            'display_name' => $this->display_name,
            'source_type' => $this->source_type,
            'normalized_type' => $this->normalized_type,
            'semantic_type' => $this->semantic_type,
            'is_dimension' => $this->is_dimension,
            'is_metric' => $this->is_metric,
            'is_visible' => $this->is_visible,
            'is_filterable' => $this->is_filterable,
            'default_aggregate' => $this->default_aggregate,
            'expression' => $this->expression,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
