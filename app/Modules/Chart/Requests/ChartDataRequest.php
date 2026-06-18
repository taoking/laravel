<?php

namespace App\Modules\Chart\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartDataRequest extends FormRequest
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
            'filters' => ['nullable', 'array'],
            'filters.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'filters.*.operator' => ['required', 'string', Rule::in(['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like', 'not_like', 'between', 'is_null', 'is_not_null'])],
            'filters.*.value' => ['nullable'],
            'sorts' => ['nullable', 'array'],
            'sorts.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'sorts.*.direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'use_cache' => ['nullable', 'boolean'],
        ];
    }
}
