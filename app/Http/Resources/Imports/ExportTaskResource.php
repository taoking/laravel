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
            'total_rows' => $this->total_rows,
            'processed_rows' => $this->processed_rows,
            'progress_percentage' => $this->progressPercentage(),
            'file_size' => $this->file_size,
            'attempts' => $this->attempts,
            'error_message' => $this->error_message,
            'failure_type' => $this->failure_type,
            'last_failed_at' => $this->last_failed_at?->toISOString(),
            'download_url' => $this->status === 'completed'
                ? route('api.v1.exports.download', ['export' => $this->id], false)
                : null,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'downloaded_at' => $this->downloaded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function progressPercentage(): int
    {
        if ($this->status === 'completed') {
            return 100;
        }

        if ((int) $this->total_rows === 0) {
            return $this->status === 'processing' ? 1 : 0;
        }

        return min(99, (int) floor(((int) $this->processed_rows / (int) $this->total_rows) * 100));
    }
}
