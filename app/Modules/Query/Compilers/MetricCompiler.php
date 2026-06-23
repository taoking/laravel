<?php

namespace App\Modules\Query\Compilers;

use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Query\Dialects\SqlDialectInterface;
use App\Modules\Query\DTO\MetricDTO;

class MetricCompiler
{
    /**
     * @return array{select: string, alias: string, column: array{name: string, label: string, type: string}}
     */
    public function compile(MetricDTO $metric, DatasetField $field, SqlDialectInterface $dialect): array
    {
        $aggregate = $metric->aggregate ?? ($field->default_aggregate !== 'none' ? $field->default_aggregate : 'count');
        $alias = $metric->alias ?? "{$metric->field}_{$aggregate}";
        $expression = $dialect->compileAggregate($aggregate, $dialect->quoteIdentifier($field->field_name));

        return [
            'select' => "{$expression} as ".$dialect->quoteIdentifier($alias),
            'alias' => $alias,
            'column' => [
                'name' => $alias,
                'label' => $field->display_name,
                'type' => $this->metricType($aggregate, $field->normalized_type),
            ],
        ];
    }

    private function metricType(string $aggregate, string $normalizedType): string
    {
        if (in_array($aggregate, ['count', 'countDistinct'], true)) {
            return 'integer';
        }

        return in_array($normalizedType, ['integer', 'decimal', 'number'], true) ? 'number' : $normalizedType;
    }
}
