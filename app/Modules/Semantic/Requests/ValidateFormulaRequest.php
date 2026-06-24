<?php

namespace App\Modules\Semantic\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateFormulaRequest extends FormRequest
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
            'formula' => ['required', 'string'],
        ];
    }
}
