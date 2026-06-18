<?php

namespace App\Modules\Dashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareDashboardRequest extends FormRequest
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
            'share_type' => ['nullable', 'string', Rule::in(['public', 'password'])],
            'password' => ['required_if:share_type,password', 'nullable', 'string', 'min:6', 'max:255'],
            'expired_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
