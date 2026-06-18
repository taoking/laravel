<?php

namespace App\Modules\DataPermission\Requests;

use Illuminate\Validation\Rule;

class UpdateColumnPermissionRuleRequest extends StoreColumnPermissionRuleRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'dataset_id' => ['sometimes', 'required', 'integer', 'exists:datasets,id'],
            'subject_type' => ['sometimes', 'required', Rule::in(['user', 'role', 'department', 'organization'])],
            'subject_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'field_name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'permission_type' => ['sometimes', 'required', Rule::in(['visible', 'hidden', 'masked'])],
        ];
    }
}
