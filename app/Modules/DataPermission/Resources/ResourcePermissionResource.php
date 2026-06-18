<?php

namespace App\Modules\DataPermission\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourcePermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'permission_type' => $this->permission_type,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
