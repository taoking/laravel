<?php

namespace App\Modules\Audit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueryLogResource extends JsonResource
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
            'dataset_id' => $this->dataset_id,
            'chart_id' => $this->chart_id,
            'dashboard_id' => $this->dashboard_id,
            'sql' => $this->sql,
            'bindings_json' => $this->bindings_json,
            'query_hash' => $this->query_hash,
            'elapsed_ms' => $this->elapsed_ms,
            'row_count' => $this->row_count,
            'cached' => $this->cached,
            'is_slow' => $this->is_slow,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
