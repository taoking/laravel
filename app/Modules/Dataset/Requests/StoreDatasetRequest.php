<?php

namespace App\Modules\Dataset\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatasetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'data_source_id' => ['required', 'integer', 'exists:data_sources,id'],
            'dataset_type' => ['nullable', Rule::in(['single_table'])],
            'main_table' => ['required', 'string', 'max:255'],
            'table_alias' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ];
    }
}
