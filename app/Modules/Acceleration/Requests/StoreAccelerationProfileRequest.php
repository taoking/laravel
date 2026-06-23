<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccelerationProfileRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'engine_type' => ['nullable', Rule::in(['clickhouse', 'starrocks', 'doris', 'mysql_summary'])],
            'mode' => ['nullable', Rule::in(['detail_table', 'aggregate_table', 'materialized_view'])],
            'status' => ['nullable', Rule::in(['disabled', 'building', 'active', 'failed'])],
            'source_connection_id' => ['nullable', 'integer', 'exists:data_sources,id'],
            'target_connection_id' => ['nullable', 'integer', 'exists:data_sources,id'],
            'target_database' => ['nullable', 'string', 'max:128'],
            'target_table' => ['nullable', 'string', 'max:128'],
            'refresh_type' => ['nullable', Rule::in(['manual', 'scheduled', 'on_import_completed'])],
            'refresh_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'config_json' => ['nullable', 'array'],
            'columns' => ['nullable', 'array'],
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
