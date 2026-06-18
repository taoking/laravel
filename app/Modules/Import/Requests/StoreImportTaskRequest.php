<?php

namespace App\Modules\Import\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreImportTaskRequest extends FormRequest
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
            'tenant_id' => ['nullable', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:51200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            $extension = strtolower($file?->getClientOriginalExtension() ?? '');

            if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
                $validator->errors()->add('file', 'The file must be a CSV or XLSX file.');
            }
        });
    }
}
