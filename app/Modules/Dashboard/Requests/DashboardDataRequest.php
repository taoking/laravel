<?php

namespace App\Modules\Dashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardDataRequest extends FormRequest
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
            'filters' => ['nullable', 'array'],
            'filters.*.field' => ['required_with:filters', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'filters.*.operator' => ['required_with:filters', 'string', Rule::in(['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like', 'not_like', 'between', 'is_null', 'is_not_null'])],
            'filters.*.value' => ['nullable'],
        ];
    }
}
