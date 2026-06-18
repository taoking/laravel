<?php

namespace App\Modules\Dashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardWidgetRequest extends FormRequest
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
            'chart_id' => ['required', 'integer', 'exists:charts,id'],
            'widget_type' => ['nullable', 'string', Rule::in(['chart'])],
            'x' => ['nullable', 'integer', 'min:0'],
            'y' => ['nullable', 'integer', 'min:0'],
            'w' => ['nullable', 'integer', 'min:1'],
            'h' => ['nullable', 'integer', 'min:1'],
            'config_json' => ['nullable', 'array'],
            'config_json.query_overrides' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
