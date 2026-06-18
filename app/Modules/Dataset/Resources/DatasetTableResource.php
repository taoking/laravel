<?php

namespace App\Modules\Dataset\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatasetTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dataset_id' => $this->dataset_id,
            'data_source_id' => $this->data_source_id,
            'table_name' => $this->table_name,
            'alias' => $this->alias,
            'join_type' => $this->join_type,
            'join_condition' => $this->join_condition,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
