<?php

namespace App\Modules\Chart\Requests;

use App\Modules\Chart\Services\ChartConfigValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartRequest extends FormRequest
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
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'chart_type' => ['required', 'string', Rule::in(ChartConfigValidator::CHART_TYPES)],
            'config_json' => ['required', 'array'],
            'config_json.dimensions' => ['nullable', 'array'],
            'config_json.dimensions.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json.dimensions.*.time_granularity' => ['nullable', 'string', Rule::in(['year', 'quarter', 'month', 'week', 'day', 'hour', 'minute'])],
            'config_json.dimensions.*.alias' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json.metrics' => ['nullable', 'array'],
            'config_json.metrics.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json.metrics.*.aggregate' => ['nullable', 'string', Rule::in(['sum', 'avg', 'count', 'max', 'min'])],
            'config_json.metrics.*.alias' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json.filters' => ['nullable', 'array'],
            'config_json.filters.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json.filters.*.operator' => ['required', 'string', Rule::in(['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like', 'not_like', 'between', 'is_null', 'is_not_null'])],
            'config_json.filters.*.value' => ['nullable'],
            'config_json.sorts' => ['nullable', 'array'],
            'config_json.sorts.*.field' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'config_json.sorts.*.direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'config_json.limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'config_json.offset' => ['nullable', 'integer', 'min:0'],
            'config_json.use_cache' => ['nullable', 'boolean'],
            'style_json' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ];
    }
}
