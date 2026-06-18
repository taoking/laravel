<?php

namespace App\Modules\Audit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationLogResource extends JsonResource
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
            'action' => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'request_method' => $this->request_method,
            'request_url' => $this->request_url,
            'request_ip' => $this->request_ip,
            'request_user_agent' => $this->request_user_agent,
            'request_payload_json' => $this->request_payload_json,
            'response_code' => $this->response_code,
            'elapsed_ms' => $this->elapsed_ms,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
