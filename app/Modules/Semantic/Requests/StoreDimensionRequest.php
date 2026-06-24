<?php

namespace App\Modules\Semantic\Requests;

use App\Modules\Semantic\Models\Dimension;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDimensionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'field_name' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'dimension_type' => ['required', Rule::in(Dimension::TYPES)],
            'time_grain_options_json' => ['nullable', 'array'],
            'time_grain_options_json.*' => ['string', Rule::in(['year', 'quarter', 'month', 'week', 'day', 'hour', 'minute'])],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(Dimension::STATUSES)],
        ];
    }
}
