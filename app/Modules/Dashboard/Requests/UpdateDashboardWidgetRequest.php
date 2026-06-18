<?php

namespace App\Modules\Dashboard\Requests;

use Illuminate\Validation\Rule;

class UpdateDashboardWidgetRequest extends StoreDashboardWidgetRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'chart_id' => ['sometimes', 'required', 'integer', 'exists:charts,id'],
            'widget_type' => ['sometimes', 'required', 'string', Rule::in(['chart'])],
            'x' => ['sometimes', 'required', 'integer', 'min:0'],
            'y' => ['sometimes', 'required', 'integer', 'min:0'],
            'w' => ['sometimes', 'required', 'integer', 'min:1'],
            'h' => ['sometimes', 'required', 'integer', 'min:1'],
            'config_json' => ['sometimes', 'nullable', 'array'],
            'config_json.query_overrides' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
