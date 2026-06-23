<?php

namespace App\Modules\Query\Compilers;

use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Query\Dialects\SqlDialectInterface;
use App\Modules\Query\DTO\DimensionDTO;

class DimensionCompiler
{
    /**
     * @return array{select: string, group_by: string, alias: string, column: array{name: string, label: string, type: string}}
     */
    public function compile(DimensionDTO $dimension, DatasetField $field, SqlDialectInterface $dialect): array
    {
        $expression = $this->expression($dimension, $field, $dialect);
        $alias = $dimension->alias ?? $dimension->field;

        return [
            'select' => "{$expression} as ".$dialect->quoteIdentifier($alias),
            'group_by' => $expression,
            'alias' => $alias,
            'column' => [
                'name' => $alias,
                'label' => $field->display_name,
                'type' => $field->normalized_type,
            ],
        ];
    }

    private function expression(DimensionDTO $dimension, DatasetField $field, SqlDialectInterface $dialect): string
    {
        $quotedField = $dialect->quoteIdentifier($field->field_name);

        return $dimension->timeGranularity !== null
            ? $dialect->compileDateGrain($quotedField, $dimension->timeGranularity)
            : $quotedField;
    }
}
