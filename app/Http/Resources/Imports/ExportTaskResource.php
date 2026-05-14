<?php

namespace App\Http\Resources\Imports;

use App\Domains\Imports\Models\ExportTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExportTask
 */
class ExportTaskResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'type' => $this->type,
            'status' => $this->status,
            'filters' => $this->filters,
            'path' => $this->path,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
