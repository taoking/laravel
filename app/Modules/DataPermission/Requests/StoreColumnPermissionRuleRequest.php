<?php

namespace App\Modules\DataPermission\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColumnPermissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', 'min:1'],
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'subject_type' => ['required', Rule::in(['user', 'role', 'department', 'organization'])],
            'subject_id' => ['required', 'integer', 'min:1'],
            'field_name' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'permission_type' => ['required', Rule::in(['visible', 'hidden', 'masked'])],
        ];
    }
}
