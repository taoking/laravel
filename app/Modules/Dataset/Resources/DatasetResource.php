<?php

namespace App\Modules\Dataset\Resources;

use App\Modules\DataSource\Resources\DataSourceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatasetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'data_source_id' => $this->data_source_id,
            'data_source' => $this->whenLoaded(
                'dataSource',
                fn () => (new DataSourceResource($this->dataSource))->resolve($request),
            ),
            'dataset_type' => $this->dataset_type,
            'main_table' => $this->main_table,
            'config_json' => $this->config_json,
            'status' => $this->status,
            'tables_count' => $this->whenCounted('tables'),
            'fields_count' => $this->whenCounted('fields'),
            'tables' => DatasetTableResource::collection($this->whenLoaded('tables')),
            'fields' => DatasetFieldResource::collection($this->whenLoaded('fields')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
