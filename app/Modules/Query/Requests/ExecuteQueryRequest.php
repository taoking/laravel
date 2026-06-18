<?php

namespace App\Modules\Query\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteQueryRequest extends FormRequest
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
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'dimensions.*.time_granularity' => ['nullable', 'string', Rule::in(['year', 'quarter', 'month', 'week', 'day', 'hour', 'minute'])],
            'dimensions.*.alias' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'metrics' => ['nullable', 'array'],
            'metrics.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'metrics.*.aggregate' => ['nullable', 'string', Rule::in(['sum', 'avg', 'count', 'max', 'min'])],
            'metrics.*.alias' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
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
