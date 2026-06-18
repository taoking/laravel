<?php

namespace App\Modules\Export\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExportTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'export_type' => $this->export_type,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'status' => $this->status,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'progress' => $this->progress,
            'error_message' => $this->error_message,
            'created_by' => $this->created_by,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
