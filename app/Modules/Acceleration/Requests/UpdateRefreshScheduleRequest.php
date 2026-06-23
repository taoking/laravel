<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefreshScheduleRequest extends FormRequest
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
            'target_type' => ['sometimes', Rule::in(['detail_profile', 'aggregate_definition'])],
            'target_id' => ['sometimes', 'integer', 'min:1'],
            'refresh_type' => ['sometimes', Rule::in(['manual', 'hourly', 'daily', 'weekly', 'cron'])],
            'cron_expression' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
            'next_run_at' => ['nullable', 'date'],
        ];
    }
}
