<?php

namespace App\Modules\Acceleration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateRecommendationRequest extends FormRequest
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
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'dataset_id' => ['nullable', 'integer', 'exists:datasets,id'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}
