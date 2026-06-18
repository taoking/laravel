<?php

namespace App\Modules\User\Resources;

use App\Modules\Permission\Resources\PermissionResource;
use App\Modules\Permission\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'department_id' => $this->department_id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => $this->whenLoaded('roles', function () {
                $permissions = $this->roles
                    ->flatMap(fn ($role) => $role->permissions)
                    ->unique('id')
                    ->values();

                return PermissionResource::collection($permissions);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
