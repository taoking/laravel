<?php

namespace App\Modules\Query\Compilers;

use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Query\DTO\MetricDTO;

class MetricCompiler
{
    public function __construct(private readonly SqlIdentifier $identifier) {}

    /**
     * @return array{select: string, alias: string, column: array{name: string, label: string, type: string}}
     */
    public function compile(MetricDTO $metric, DatasetField $field): array
    {
        $aggregate = $metric->aggregate ?? ($field->default_aggregate !== 'none' ? $field->default_aggregate : 'count');
        $alias = $metric->alias ?? "{$metric->field}_{$aggregate}";
        $expression = sprintf('%s(%s)', $aggregate, $this->identifier->quote($field->field_name));

        return [
            'select' => "{$expression} as ".$this->identifier->quote($alias),
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
        if ($aggregate === 'count') {
            return 'integer';
        }

        return in_array($normalizedType, ['integer', 'decimal', 'number'], true) ? 'number' : $normalizedType;
    }
}
