<?php

namespace App\Modules\Semantic\Requests;

use App\Modules\Semantic\Models\Dimension;
use Illuminate\Validation\Rule;

class UpdateDimensionRequest extends StoreDimensionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dataset_id' => ['sometimes', 'required', 'integer', 'exists:datasets,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'field_name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'dimension_type' => ['sometimes', 'required', Rule::in(Dimension::TYPES)],
            'time_grain_options_json' => ['sometimes', 'nullable', 'array'],
            'time_grain_options_json.*' => ['string', Rule::in(['year', 'quarter', 'month', 'week', 'day', 'hour', 'minute'])],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(Dimension::STATUSES)],
        ];
    }
}
