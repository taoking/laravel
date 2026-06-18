<?php

namespace App\Modules\Dashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardRequest extends FormRequest
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
            'layout_json' => ['nullable', 'array'],
            'layout_json.*.widget_id' => ['required_with:layout_json', 'integer'],
            'layout_json.*.x' => ['required_with:layout_json', 'integer', 'min:0'],
            'layout_json.*.y' => ['required_with:layout_json', 'integer', 'min:0'],
            'layout_json.*.w' => ['required_with:layout_json', 'integer', 'min:1'],
            'layout_json.*.h' => ['required_with:layout_json', 'integer', 'min:1'],
            'global_filters_json' => ['nullable', 'array'],
            'global_filters_json.*.field' => ['required_with:global_filters_json', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'global_filters_json.*.operator' => ['required_with:global_filters_json', 'string', Rule::in(['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like', 'not_like', 'between', 'is_null', 'is_not_null'])],
            'global_filters_json.*.value' => ['nullable'],
            'filters' => ['nullable', 'array'],
            'filters.*.field_name' => ['required_with:filters', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'filters.*.label' => ['required_with:filters', 'string', 'max:255'],
            'filters.*.filter_type' => ['nullable', 'string', Rule::in(['select', 'multi_select', 'date_range', 'input'])],
            'filters.*.default_value_json' => ['nullable'],
            'filters.*.config_json' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ];
    }
}
