<?php

namespace App\Modules\Query\Compilers;

use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Query\DTO\DimensionDTO;

class DimensionCompiler
{
    public function __construct(private readonly SqlIdentifier $identifier) {}

    /**
     * @return array{select: string, group_by: string, alias: string, column: array{name: string, label: string, type: string}}
     */
    public function compile(DimensionDTO $dimension, DatasetField $field): array
    {
        $expression = $this->expression($dimension, $field);
        $alias = $dimension->alias ?? $dimension->field;

        return [
            'select' => "{$expression} as ".$this->identifier->quote($alias),
            'group_by' => $expression,
            'alias' => $alias,
            'column' => [
                'name' => $alias,
                'label' => $field->display_name,
                'type' => $field->normalized_type,
            ],
        ];
    }

    private function expression(DimensionDTO $dimension, DatasetField $field): string
    {
        $quotedField = $this->identifier->quote($field->field_name);

        return match ($dimension->timeGranularity) {
            'year' => "year({$quotedField})",
            'quarter' => "concat(year({$quotedField}), '-Q', quarter({$quotedField}))",
            'month' => "date_format({$quotedField}, '%Y-%m')",
            'week' => "yearweek({$quotedField}, 3)",
            'day' => "date_format({$quotedField}, '%Y-%m-%d')",
            'hour' => "date_format({$quotedField}, '%Y-%m-%d %H:00:00')",
            'minute' => "date_format({$quotedField}, '%Y-%m-%d %H:%i:00')",
            default => $quotedField,
        };
    }
}
