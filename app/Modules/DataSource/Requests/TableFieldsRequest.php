<?php

namespace App\Modules\DataSource\Requests;

use App\Modules\DataSource\Services\IdentifierGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TableFieldsRequest extends FormRequest
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
            'table' => ['required', 'string', 'max:255'],
        ];
    }

    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'table' => $this->route('table'),
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $table = (string) $this->route('table');

                if (! IdentifierGuard::isSafe($table)) {
                    $validator->errors()->add('table', 'The table name is not allowed.');
                }
            },
        ];
    }
}
