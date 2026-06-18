<?php

namespace App\Modules\Import\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'success_rows' => $this->success_rows,
            'failed_rows' => $this->failed_rows,
            'progress' => $this->progress,
            'error_message' => $this->error_message,
            'created_by' => $this->created_by,
            'logs_count' => $this->whenCounted('logs'),
            'uploaded_table' => $this->whenLoaded(
                'uploadedTable',
                fn () => (new UploadedTableResource($this->uploadedTable))->resolve($request),
            ),
            'logs' => ImportTaskLogResource::collection($this->whenLoaded('logs')),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
