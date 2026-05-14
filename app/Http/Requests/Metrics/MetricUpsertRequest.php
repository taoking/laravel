<?php

namespace App\Http\Requests\Metrics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetricUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $metricId = $this->route('metric')?->id;

        return [
            'metric_category_id' => ['required', 'integer', 'exists:metric_categories,id'],
            'name' => ['required', 'string', 'max:160', 'not_regex:/<\s*script/i'],
            'code' => ['required', 'string', 'max:120', Rule::unique('metrics', 'code')->ignore($metricId)],
            'unit' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'description' => ['nullable', 'string', 'not_regex:/<\s*script/i'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
