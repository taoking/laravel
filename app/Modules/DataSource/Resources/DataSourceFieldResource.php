<?php

namespace App\Modules\DataSource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataSourceFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data_source_id' => $this->data_source_id,
            'table_id' => $this->table_id,
            'table_name' => $this->table_name,
            'field_name' => $this->field_name,
            'field_comment' => $this->field_comment,
            'data_type' => $this->data_type,
            'normalized_type' => $this->normalized_type,
            'is_nullable' => $this->is_nullable,
            'is_primary_key' => $this->is_primary_key,
            'default_value' => $this->default_value,
            'ordinal_position' => $this->ordinal_position,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
