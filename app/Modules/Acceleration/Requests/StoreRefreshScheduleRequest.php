<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefreshScheduleRequest extends FormRequest
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
            'target_type' => ['required', Rule::in(['detail_profile', 'aggregate_definition'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'refresh_type' => ['required', Rule::in(['manual', 'hourly', 'daily', 'weekly', 'cron'])],
            'cron_expression' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
            'next_run_at' => ['nullable', 'date'],
        ];
    }
}
