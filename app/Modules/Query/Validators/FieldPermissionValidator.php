<?php

namespace App\Modules\Query\Validators;

use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FieldPermissionValidator
{
    /**
     * @param  Collection<string, DatasetField>  $fieldsByName
     */
    public function assertFieldExists(Dataset $dataset, Collection $fieldsByName, string $fieldName): DatasetField
    {
        $field = $fieldsByName->get($fieldName);

        if (! $field || $field->dataset_id !== $dataset->id) {
            throw ValidationException::withMessages([
                'fields' => ["Field [{$fieldName}] does not belong to this dataset."],
            ]);
        }

        return $field;
    }
}
