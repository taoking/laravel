<?php

namespace App\Modules\Semantic\Requests;

use App\Modules\Semantic\Models\Metric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetricRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', 'exists:metric_categories,id'],
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/', Rule::unique('metrics', 'code')],
            'description' => ['nullable', 'string'],
            'metric_type' => ['required', Rule::in(Metric::TYPES)],
            'aggregate_function' => ['nullable', Rule::in(Metric::AGGREGATES)],
            'source_field' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'formula' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:64'],
            'precision' => ['nullable', 'integer', 'min:0', 'max:8'],
            'format_type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(Metric::STATUSES)],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
