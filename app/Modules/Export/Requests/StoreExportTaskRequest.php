<?php

namespace App\Modules\Export\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExportTaskRequest extends FormRequest
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
            'export_type' => ['required', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
            'source_type' => ['required', 'string', Rule::in(['chart', 'dashboard'])],
            'source_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sourceType = (string) $this->input('source_type');
            $exportType = (string) $this->input('export_type');

            if ($sourceType === 'chart' && ! in_array($exportType, ['csv', 'xlsx'], true)) {
                $validator->errors()->add('export_type', 'Chart exports support CSV and XLSX.');
            }

            if ($sourceType === 'dashboard' && $exportType !== 'pdf') {
                $validator->errors()->add('export_type', 'Dashboard exports support PDF.');
            }
        });
    }
}
