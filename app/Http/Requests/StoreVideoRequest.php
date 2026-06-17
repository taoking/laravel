<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Laravel's max rule for files is measured in kilobytes. 102400 KB = 100 MB.
            'video' => ['required', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
        ];
    }
}
