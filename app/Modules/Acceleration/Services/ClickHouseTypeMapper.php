<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Dataset\Models\DatasetField;

class ClickHouseTypeMapper
{
    public function clickHouseType(DatasetField $field, bool $nullable = true): string
    {
        $type = $this->baseType($field);

        return $nullable ? "Nullable({$type})" : $type;
    }

    private function baseType(DatasetField $field): string
    {
        $source = strtolower($field->source_type.' '.$field->normalized_type);

        if (str_contains($source, 'bigint')) {
            return 'Int64';
        }

        if ($field->normalized_type === 'boolean') {
            return 'UInt8';
        }

        if (str_contains($source, 'tinyint')) {
            return 'Int8';
        }

        if (str_contains($source, 'int') || $field->normalized_type === 'integer') {
            return 'Int64';
        }

        if (str_contains($source, 'decimal') || $field->normalized_type === 'decimal') {
            return 'Decimal(18, 4)';
        }

        if (str_contains($source, 'float') || str_contains($source, 'double') || $field->normalized_type === 'number') {
            return 'Float64';
        }

        if ($field->normalized_type === 'date') {
            return 'Date';
        }

        if (in_array($field->normalized_type, ['datetime', 'timestamp'], true)) {
            return 'DateTime';
        }

        return 'String';
    }
}
