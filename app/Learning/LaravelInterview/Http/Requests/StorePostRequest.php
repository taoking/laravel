<?php

namespace App\Learning\LaravelInterview\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 面试点：认证是“用户是谁”，授权是“用户能不能做这件事”。
        return $this->user() !== null || app()->environment('local');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'alpha_dash',
                'max:140',
                Rule::unique('interview_example_posts', 'slug')->ignore($this->route('post')),
            ],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:20'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => $this->boolean('is_published')),
            ],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'published_at.required' => '发布文章时必须提供发布时间。',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
            'is_published' => $this->boolean('is_published'),
        ]);
    }
}
