<?php

namespace App\Modules\DataSource\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDataSourceRequest extends FormRequest
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
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['mysql', 'starrocks', 'doris'])],
            'host' => ['sometimes', 'required', 'string', 'max:255'],
            'port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'charset' => ['sometimes', 'required', 'string', 'max:64'],
            'timezone' => ['sometimes', 'required', 'string', 'max:64'],
            'options_json' => ['sometimes', 'nullable', 'array'],
            'options_json.timeout' => ['nullable', 'integer', 'min:1', 'max:30'],
            'options_json.ssl_enabled' => ['nullable', 'boolean'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'disabled'])],
        ];
    }
}
