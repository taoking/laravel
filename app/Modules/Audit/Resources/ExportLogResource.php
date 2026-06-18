<?php

namespace App\Modules\Audit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExportLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'export_task_id' => $this->export_task_id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'file_path' => $this->file_path,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
