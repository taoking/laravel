<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationColumn;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Services\IdentifierGuard;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\FilterDTO;
use App\Modules\Query\DTO\MetricDTO;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ClickHouseSqlGenerator
{
    public function generate(LogicalQueryPlan $plan, AccelerationProfile $profile): CompiledQuery
    {
        $profile->loadMissing('columns');
        $plan->dataset->loadMissing('fields');

        /** @var Collection<string, AccelerationColumn> $columnsBySource */
        $columnsBySource = $profile->columns->keyBy('source_field_name');
        /** @var Collection<string, DatasetField> $fieldsByName */
        $fieldsByName = $plan->dataset->fields->keyBy('field_name');
        $selects = [];
        $groupBys = [];
        $wheres = [];
        $columns = [];

        foreach ($plan->query->dimensions as $dimension) {
            $field = $fieldsByName->get($dimension->field);
            $column = $columnsBySource->get($dimension->field);
            $alias = $dimension->alias ?? $dimension->field;
            $expression = $this->dimensionExpression($column, $dimension->timeGranularity);
            $selects[] = "{$expression} as ".$this->quoteIdentifier($alias);
            $groupBys[] = $expression;
            $columns[] = [
                'name' => $alias,
                'label' => $field?->display_name ?? $alias,
                'type' => $field?->normalized_type ?? 'unknown',
            ];
        }

        foreach ($plan->query->metrics as $metric) {
            $field = $fieldsByName->get($metric->field);
            $column = $columnsBySource->get($metric->field);
            $aggregate = $metric->aggregate ?? ($field?->default_aggregate !== 'none' ? $field?->default_aggregate : 'count');
            $alias = $metric->alias ?? "{$metric->field}_{$aggregate}";
            $selects[] = $this->metricExpression($metric, $column, (string) $aggregate).' as '.$this->quoteIdentifier($alias);
            $columns[] = [
                'name' => $alias,
                'label' => $field?->display_name ?? $alias,
                'type' => $aggregate === 'count' || $aggregate === 'countDistinct' ? 'integer' : 'number',
            ];
        }

        foreach ([...$plan->query->filters, ...$plan->permissionFilters] as $filter) {
            $column = $columnsBySource->get($filter->field);

            if ($column === null) {
                throw new InvalidArgumentException("Acceleration column mapping missing for [{$filter->field}].");
            }

            $wheres[] = $this->filterExpression($filter, $column);
        }

        $sqlParts = [
            'select '.implode(', ', $selects),
            'from '.$this->qualifiedTable($profile),
        ];

        if ($wheres !== []) {
            $sqlParts[] = 'where '.implode(' and ', $wheres);
        }

        if ($groupBys !== []) {
            $sqlParts[] = 'group by '.implode(', ', $groupBys);
        }

        if ($plan->query->sorts !== []) {
            $sqlParts[] = 'order by '.collect($plan->query->sorts)
                ->map(fn ($sort): string => $this->quoteIdentifier($sort->field).' '.$sort->direction)
                ->implode(', ');
        }

        $sqlParts[] = "limit {$plan->query->limit} offset {$plan->query->offset}";
        $sql = implode(' ', $sqlParts);

        return new CompiledQuery(
            sql: $sql,
            bindings: [],
            columns: $columns,
            hash: sha1('acceleration|'.$profile->id.'|'.$profile->version.'|'.$sql),
        );
    }

    private function dimensionExpression(AccelerationColumn $column, ?string $timeGranularity): string
    {
        $quoted = $this->quoteIdentifier($column->target_field_name);

        return match ($timeGranularity) {
            'year' => "toYear({$quoted})",
            'quarter' => "concat(toString(toYear({$quoted})), '-Q', toString(toQuarter({$quoted})))",
            'month' => "toStartOfMonth({$quoted})",
            'week' => "toStartOfWeek({$quoted})",
            'day' => "toDate({$quoted})",
            'hour' => "toStartOfHour({$quoted})",
            'minute' => "toStartOfMinute({$quoted})",
            default => $quoted,
        };
    }

    private function metricExpression(MetricDTO $metric, AccelerationColumn $column, string $aggregate): string
    {
        $quoted = $this->quoteIdentifier($column->target_field_name);

        return match ($aggregate) {
            'countDistinct' => "countDistinct({$quoted})",
            default => "{$aggregate}({$quoted})",
        };
    }

    private function filterExpression(FilterDTO $filter, AccelerationColumn $column): string
    {
        $quoted = $this->quoteIdentifier($column->target_field_name);

        return match ($filter->operator) {
            '=' => "{$quoted} = ".$this->literal($filter->value),
            '!=' => "{$quoted} != ".$this->literal($filter->value),
            '>' => "{$quoted} > ".$this->literal($filter->value),
            '>=' => "{$quoted} >= ".$this->literal($filter->value),
            '<' => "{$quoted} < ".$this->literal($filter->value),
            '<=' => "{$quoted} <= ".$this->literal($filter->value),
            'like' => "{$quoted} like ".$this->literal($filter->value),
            'not_like' => "{$quoted} not like ".$this->literal($filter->value),
            'in' => "{$quoted} in (".$this->literalList((array) $filter->value).')',
            'not_in' => "{$quoted} not in (".$this->literalList((array) $filter->value).')',
            'between' => $this->betweenExpression($quoted, (array) $filter->value),
            'is_null' => "{$quoted} is null",
            'is_not_null' => "{$quoted} is not null",
            default => throw new InvalidArgumentException("Unsupported filter operator [{$filter->operator}]."),
        };
    }

    /**
     * @param  list<mixed>  $values
     */
    private function betweenExpression(string $quoted, array $values): string
    {
        return "{$quoted} between ".$this->literal($values[0] ?? null).' and '.$this->literal($values[1] ?? null);
    }

    /**
     * @param  list<mixed>  $values
     */
    private function literalList(array $values): string
    {
        return collect($values)->map(fn (mixed $value): string => $this->literal($value))->implode(', ');
    }

    private function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value);

        return "'{$escaped}'";
    }

    private function qualifiedTable(AccelerationProfile $profile): string
    {
        $database = $profile->target_database ?: (string) config('bi_acceleration.clickhouse.database');

        return $this->quoteIdentifier($database).'.'.$this->quoteIdentifier($profile->target_table);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw new InvalidArgumentException("Unsafe ClickHouse identifier [{$identifier}].");
        }

        return '`'.$identifier.'`';
    }
}
