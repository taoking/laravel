<?php

namespace App\Modules\DataPermission\Requests;

use Illuminate\Validation\Rule;

class UpdateDataPermissionRuleRequest extends StoreDataPermissionRuleRequest
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
            'operator' => ['sometimes', 'required', Rule::in(['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like', 'not_like', 'between', 'is_null', 'is_not_null'])],
            'value_type' => ['sometimes', 'nullable', Rule::in(['static'])],
            'value_json' => ['sometimes', 'nullable'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'disabled'])],
        ];
    }
}
