<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccelerationProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'engine_type' => ['sometimes', Rule::in(['clickhouse', 'starrocks', 'doris', 'mysql_summary'])],
            'mode' => ['sometimes', Rule::in(['detail_table', 'aggregate_table', 'materialized_view'])],
            'status' => ['sometimes', Rule::in(['disabled', 'building', 'active', 'failed'])],
            'source_connection_id' => ['sometimes', 'nullable', 'integer', 'exists:data_sources,id'],
            'target_connection_id' => ['sometimes', 'nullable', 'integer', 'exists:data_sources,id'],
            'target_database' => ['sometimes', 'nullable', 'string', 'max:128'],
            'target_table' => ['sometimes', 'string', 'max:128'],
            'refresh_type' => ['sometimes', Rule::in(['manual', 'scheduled', 'on_import_completed'])],
            'refresh_interval_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10080'],
            'config_json' => ['sometimes', 'nullable', 'array'],
            'columns' => ['sometimes', 'array'],
            'columns.*.dataset_field_id' => ['nullable', 'integer', 'exists:dataset_fields,id'],
            'columns.*.source_field_name' => ['required_with:columns', 'string', 'max:128'],
            'columns.*.target_field_name' => ['nullable', 'string', 'max:128'],
            'columns.*.source_type' => ['nullable', 'string', 'max:128'],
            'columns.*.target_type' => ['nullable', 'string', 'max:128'],
            'columns.*.is_dimension' => ['nullable', 'boolean'],
            'columns.*.is_metric' => ['nullable', 'boolean'],
            'columns.*.aggregate_functions_json' => ['nullable', 'array'],
            'columns.*.is_partition_key' => ['nullable', 'boolean'],
            'columns.*.is_order_key' => ['nullable', 'boolean'],
            'columns.*.is_nullable' => ['nullable', 'boolean'],
        ];
    }
}
