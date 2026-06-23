<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAggregateDefinitionRequest extends FormRequest
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
            'dataset_id' => ['nullable', 'integer', 'exists:datasets,id'],
            'detail_profile_id' => ['nullable', 'integer', 'exists:acceleration_profiles,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['disabled', 'building', 'active', 'failed'])],
            'target_database' => ['nullable', 'string', 'max:128'],
            'target_table' => ['nullable', 'string', 'max:128'],
            'time_field' => ['nullable', 'string', 'max:128'],
            'time_grain' => ['nullable', Rule::in(['none', 'day', 'month', 'year'])],
            'dimensions' => ['nullable', 'array'],
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*.field' => ['required', 'string', 'max:128'],
            'metrics.*.aggregate' => ['required', Rule::in(['sum', 'avg', 'count', 'countDistinct', 'min', 'max'])],
            'metrics.*.alias' => ['nullable', 'string', 'max:128'],
            'filters' => ['nullable', 'array'],
            'refresh_type' => ['nullable', Rule::in(['manual', 'scheduled'])],
            'columns' => ['nullable', 'array'],
        ];
    }
}
