<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuildDatasetAccelerationRequest extends FormRequest
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
            'profile_id' => ['nullable', 'integer', 'exists:acceleration_profiles,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'engine_type' => ['nullable', Rule::in(['clickhouse'])],
            'mode' => ['nullable', Rule::in(['detail_table'])],
            'target_database' => ['nullable', 'string', 'max:128'],
            'target_table' => ['nullable', 'string', 'max:128'],
            'config_json' => ['nullable', 'array'],
            'columns' => ['nullable', 'array'],
            'columns.*.source_field_name' => ['required_with:columns', 'string', 'max:128'],
            'columns.*.target_field_name' => ['nullable', 'string', 'max:128'],
            'columns.*.target_type' => ['nullable', 'string', 'max:128'],
            'columns.*.is_partition_key' => ['nullable', 'boolean'],
            'columns.*.is_order_key' => ['nullable', 'boolean'],
            'columns.*.is_nullable' => ['nullable', 'boolean'],
        ];
    }
}
