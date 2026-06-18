<?php

namespace App\Modules\DataPermission\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResourcePermissionRequest extends FormRequest
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
            'resource_type' => ['required', Rule::in(['data_source', 'dataset', 'chart', 'dashboard'])],
            'resource_id' => ['required', 'integer', 'min:1'],
            'subject_type' => ['required', Rule::in(['user', 'role', 'department', 'organization'])],
            'subject_id' => ['required', 'integer', 'min:1'],
            'permission_type' => ['required', Rule::in(['view', 'edit', 'delete', 'manage'])],
        ];
    }
}
