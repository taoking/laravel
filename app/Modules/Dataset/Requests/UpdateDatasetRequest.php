<?php

namespace App\Modules\Dataset\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatasetRequest extends FormRequest
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
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'data_source_id' => ['sometimes', 'required', 'integer', 'exists:data_sources,id'],
            'dataset_type' => ['sometimes', 'required', Rule::in(['single_table'])],
            'main_table' => ['sometimes', 'required', 'string', 'max:255'],
            'table_alias' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'disabled'])],
        ];
    }
}
