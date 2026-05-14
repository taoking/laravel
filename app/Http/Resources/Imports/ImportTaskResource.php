<?php

namespace App\Http\Resources\Imports;

use App\Domains\Imports\Models\ImportTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportTask
 */
class ImportTaskResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'original_name' => $this->original_name,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'success_rows' => $this->success_rows,
            'failed_rows' => $this->failed_rows,
            'attempts' => $this->attempts,
            'error_message' => $this->error_message,
            'failure_type' => $this->failure_type,
            'last_failed_at' => $this->last_failed_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'compensated_at' => $this->compensated_at?->toISOString(),
            'compensation_reason' => $this->compensation_reason,
            'failures' => $this->whenLoaded('failures', fn () => $this->failures->map(fn ($failure) => [
                'id' => $failure->id,
                'row_number' => $failure->row_number,
                'payload' => $failure->payload,
                'errors' => $failure->errors,
            ])->values()->all()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
