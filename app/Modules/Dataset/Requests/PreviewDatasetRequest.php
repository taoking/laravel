<?php

namespace App\Modules\Dataset\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewDatasetRequest extends FormRequest
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'max:255', 'regex:/\A[A-Za-z0-9_]+\z/'],
        ];
    }
}
