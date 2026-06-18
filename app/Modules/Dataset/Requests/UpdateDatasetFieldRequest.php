<?php

namespace App\Modules\Dataset\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatasetFieldRequest extends FormRequest
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
            'field_alias' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'normalized_type' => ['sometimes', 'required', Rule::in(['string', 'number', 'integer', 'decimal', 'date', 'datetime', 'boolean', 'json', 'unknown'])],
            'semantic_type' => ['sometimes', 'required', Rule::in(['normal', 'time', 'province', 'city', 'region', 'amount', 'count', 'rate', 'percentage', 'category', 'id'])],
            'is_dimension' => ['sometimes', 'boolean'],
            'is_metric' => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'default_aggregate' => ['sometimes', 'required', Rule::in(['none', 'sum', 'avg', 'count', 'count_distinct', 'max', 'min'])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
