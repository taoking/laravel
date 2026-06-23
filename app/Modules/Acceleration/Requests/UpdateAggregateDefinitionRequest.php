<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAggregateDefinitionRequest extends FormRequest
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
            'detail_profile_id' => ['nullable', 'integer', 'exists:acceleration_profiles,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['disabled', 'building', 'active', 'failed'])],
            'target_database' => ['nullable', 'string', 'max:128'],
            'target_table' => ['nullable', 'string', 'max:128'],
            'time_field' => ['nullable', 'string', 'max:128'],
            'time_grain' => ['sometimes', Rule::in(['none', 'day', 'month', 'year'])],
            'dimensions' => ['nullable', 'array'],
            'metrics' => ['sometimes', 'array', 'min:1'],
            'metrics.*.field' => ['required_with:metrics', 'string', 'max:128'],
            'metrics.*.aggregate' => ['required_with:metrics', Rule::in(['sum', 'avg', 'count', 'countDistinct', 'min', 'max'])],
            'metrics.*.alias' => ['nullable', 'string', 'max:128'],
            'filters' => ['nullable', 'array'],
            'refresh_type' => ['sometimes', Rule::in(['manual', 'scheduled'])],
            'columns' => ['nullable', 'array'],
        ];
    }
}
