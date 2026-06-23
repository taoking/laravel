<?php

namespace App\Modules\Query\Compilers;

use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Query\Dialects\SqlDialectInterface;
use App\Modules\Query\DTO\FilterDTO;

class FilterCompiler
{
    /**
     * @return array{sql: string, bindings: list<mixed>}
     */
    public function compile(FilterDTO $filter, DatasetField $field, SqlDialectInterface $dialect): array
    {
        $column = $dialect->quoteIdentifier($field->field_name);

        return match ($filter->operator) {
            '=' => ['sql' => "{$column} = ?", 'bindings' => [$filter->value]],
            '!=' => ['sql' => "{$column} != ?", 'bindings' => [$filter->value]],
            '>' => ['sql' => "{$column} > ?", 'bindings' => [$filter->value]],
            '>=' => ['sql' => "{$column} >= ?", 'bindings' => [$filter->value]],
            '<' => ['sql' => "{$column} < ?", 'bindings' => [$filter->value]],
            '<=' => ['sql' => "{$column} <= ?", 'bindings' => [$filter->value]],
            'like' => ['sql' => "{$column} like ?", 'bindings' => [$filter->value]],
            'not_like' => ['sql' => "{$column} not like ?", 'bindings' => [$filter->value]],
            'in' => $this->compileArrayOperator($column, 'in', (array) $filter->value),
            'not_in' => $this->compileArrayOperator($column, 'not in', (array) $filter->value),
            'between' => ['sql' => "{$column} between ? and ?", 'bindings' => array_values((array) $filter->value)],
            'is_null' => ['sql' => "{$column} is null", 'bindings' => []],
            'is_not_null' => ['sql' => "{$column} is not null", 'bindings' => []],
        };
    }

    /**
     * @param  list<mixed>  $values
     * @return array{sql: string, bindings: list<mixed>}
     */
    private function compileArrayOperator(string $column, string $operator, array $values): array
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return [
            'sql' => "{$column} {$operator} ({$placeholders})",
            'bindings' => array_values($values),
        ];
    }
}
