<?php

namespace App\Modules\Semantic\Requests;

use App\Modules\Semantic\Models\Metric;
use Illuminate\Validation\Rule;

class UpdateMetricRequest extends StoreMetricRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $metric = $this->route('metric');
        $metricId = $metric instanceof Metric ? $metric->id : null;

        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:metric_categories,id'],
            'dataset_id' => ['sometimes', 'required', 'integer', 'exists:datasets,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/', Rule::unique('metrics', 'code')->ignore($metricId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'metric_type' => ['sometimes', 'required', Rule::in(Metric::TYPES)],
            'aggregate_function' => ['sometimes', 'nullable', Rule::in(Metric::AGGREGATES)],
            'source_field' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'formula' => ['sometimes', 'nullable', 'string'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'precision' => ['sometimes', 'integer', 'min:0', 'max:8'],
            'format_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'required', Rule::in(Metric::STATUSES)],
            'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
