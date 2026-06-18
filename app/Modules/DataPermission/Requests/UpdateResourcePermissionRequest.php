<?php

namespace App\Modules\DataPermission\Requests;

use Illuminate\Validation\Rule;

class UpdateResourcePermissionRequest extends StoreResourcePermissionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'resource_type' => ['sometimes', 'required', Rule::in(['data_source', 'dataset', 'chart', 'dashboard'])],
            'resource_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'subject_type' => ['sometimes', 'required', Rule::in(['user', 'role', 'department', 'organization'])],
            'subject_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'permission_type' => ['sometimes', 'required', Rule::in(['view', 'edit', 'delete', 'manage'])],
        ];
    }
}
