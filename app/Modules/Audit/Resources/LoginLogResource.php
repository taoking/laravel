<?php

namespace App\Modules\Audit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginLogResource extends JsonResource
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
            'login_ip' => $this->login_ip,
            'user_agent' => $this->user_agent,
            'status' => $this->status,
            'message' => $this->message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
