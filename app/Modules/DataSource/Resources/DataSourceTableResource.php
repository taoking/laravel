<?php

namespace App\Modules\DataSource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataSourceTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'data_source_id' => $this->data_source_id,
            'table_name' => $this->table_name,
            'table_comment' => $this->table_comment,
            'table_type' => $this->table_type,
            'row_count_estimate' => $this->row_count_estimate,
            'synced_at' => $this->synced_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
