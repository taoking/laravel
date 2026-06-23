<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationAggregateColumn;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationColumn;
use App\Modules\DataSource\Services\IdentifierGuard;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\FilterDTO;
use App\Modules\Query\DTO\MetricDTO;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AggregateSqlGenerator
{
    public function generate(LogicalQueryPlan $plan, AccelerationAggregateDefinition $definition): CompiledQuery
    {
        $definition->loadMissing(['columns', 'dataset.fields', 'aggregateProfile']);

        /** @var Collection<string, AccelerationAggregateColumn> $dimensionColumns */
        $dimensionColumns = $definition->columns
            ->where('column_role', 'dimension')
            ->keyBy('source_field_name');
        /** @var Collection<string, AccelerationAggregateColumn> $metricColumns */
        $metricColumns = $definition->columns
            ->where('column_role', 'metric')
            ->keyBy(fn (AccelerationAggregateColumn $column): string => $this->metricKey((string) $column->source_field_name, $column->aggregate_function));
        $timeColumn = $definition->columns->firstWhere('column_role', 'time_grain');
        $fieldsByName = $definition->dataset->fields->keyBy('field_name');
        $selects = [];
        $groupBys = [];
        $wheres = [];
        $columns = [];

        foreach ($plan->query->dimensions as $dimension) {
            $field = $fieldsByName->get($dimension->field);
            $column = $this->queryDimensionColumn($definition, $dimension->field, $dimension->timeGranularity, $dimensionColumns, $timeColumn);
            $quoted = $this->quoteIdentifier($column->target_field_name);
            $alias = $dimension->alias ?? $dimension->field;
            $selects[] = "{$quoted} as ".$this->quoteIdentifier($alias);
            $groupBys[] = $quoted;
            $columns[] = [
                'name' => $alias,
                'label' => $field?->display_name ?? $alias,
                'type' => $field?->normalized_type ?? 'unknown',
            ];
        }

        foreach ($plan->query->metrics as $metric) {
            $aggregate = $this->metricAggregate($metric, $fieldsByName);
            $column = $metricColumns->get($this->metricKey($metric->field, $aggregate));

            if (! $column instanceof AccelerationAggregateColumn) {
                throw new InvalidArgumentException("Aggregate metric column mapping missing for [{$metric->field}:{$aggregate}].");
            }

            $alias = $metric->alias ?? "{$metric->field}_{$aggregate}";
            $selects[] = $this->rollupExpression($column).' as '.$this->quoteIdentifier($alias);
            $columns[] = [
                'name' => $alias,
                'label' => $fieldsByName->get($metric->field)?->display_name ?? $alias,
                'type' => in_array($aggregate, ['count', 'countDistinct'], true) ? 'integer' : 'number',
            ];
        }

        foreach ([...$plan->query->filters, ...$plan->permissionFilters] as $filter) {
            $column = $this->queryFilterColumn($definition, $filter->field, $dimensionColumns, $timeColumn);
            $wheres[] = $this->filterExpression($filter, $column->target_field_name);
        }

        $sqlParts = [
            'select '.implode(', ', $selects),
            'from '.$this->qualifiedAggregateTable($definition),
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
            hash: sha1('aggregate|'.$definition->id.'|'.$definition->version.'|'.$sql),
        );
    }

    public function createTableSql(AccelerationAggregateDefinition $definition): string
    {
        $definition->loadMissing('columns');
        $definitions = $definition->columns
            ->map(fn (AccelerationAggregateColumn $column): string => $this->quoteIdentifier($column->target_field_name).' '.$column->target_type)
            ->implode(",\n  ");

        if ($definitions === '') {
            throw new InvalidArgumentException('Cannot create an aggregate acceleration table without columns.');
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->qualifiedAggregateTable($definition)} (\n  {$definitions}\n) ENGINE = MergeTree";
        $partition = $this->partitionExpression($definition);

        if ($partition !== null) {
            $sql .= "\nPARTITION BY {$partition}";
        }

        $sql .= "\nORDER BY ".$this->orderExpression($definition);

        return $sql;
    }

    public function dropTableSql(AccelerationAggregateDefinition $definition): string
    {
        return 'DROP TABLE IF EXISTS '.$this->qualifiedAggregateTable($definition);
    }

    public function insertFromDetailSql(AccelerationAggregateDefinition $definition): string
    {
        $definition->loadMissing(['columns', 'detailProfile.columns']);
        /** @var Collection<string, AccelerationColumn> $detailColumns */
        $detailColumns = $definition->detailProfile->columns->keyBy('source_field_name');
        $targetColumns = [];
        $selects = [];
        $groupBys = [];

        foreach ($definition->columns as $column) {
            $targetColumns[] = $this->quoteIdentifier($column->target_field_name);

            if ($column->column_role === 'time_grain') {
                $detailColumn = $this->detailColumn($detailColumns, (string) $column->source_field_name);
                $expression = $this->timeGrainExpression($detailColumn->target_field_name, $definition->time_grain);
                $selects[] = "{$expression} as ".$this->quoteIdentifier($column->target_field_name);
                $groupBys[] = $expression;

                continue;
            }

            if ($column->column_role === 'dimension') {
                $detailColumn = $this->detailColumn($detailColumns, (string) $column->source_field_name);
                $expression = $this->quoteIdentifier($detailColumn->target_field_name);
                $selects[] = "{$expression} as ".$this->quoteIdentifier($column->target_field_name);
                $groupBys[] = $expression;

                continue;
            }

            $detailColumn = $this->detailColumn($detailColumns, (string) $column->source_field_name);
            $selects[] = $this->aggregateExpression($column->aggregate_function, $detailColumn->target_field_name).' as '.$this->quoteIdentifier($column->target_field_name);
        }

        $sqlParts = [
            'INSERT INTO '.$this->qualifiedAggregateTable($definition).' ('.implode(', ', $targetColumns).')',
            'select '.implode(', ', $selects),
            'from '.$this->qualifiedDetailTable($definition),
        ];

        $wheres = $this->definitionFilterExpressions($definition, $detailColumns);

        if ($wheres !== []) {
            $sqlParts[] = 'where '.implode(' and ', $wheres);
        }

        if ($groupBys !== []) {
            $sqlParts[] = 'group by '.implode(', ', $groupBys);
        }

        return implode(' ', $sqlParts);
    }

    public function countSql(AccelerationAggregateDefinition $definition): string
    {
        return 'select count() as row_count from '.$this->qualifiedAggregateTable($definition);
    }

    public function existsSql(AccelerationAggregateDefinition $definition): string
    {
        return 'EXISTS TABLE '.$this->qualifiedAggregateTable($definition);
    }

    public function createDatabaseSql(AccelerationAggregateDefinition $definition): string
    {
        return 'CREATE DATABASE IF NOT EXISTS '.$this->quoteIdentifier($this->database($definition));
    }

    public function database(AccelerationAggregateDefinition $definition): string
    {
        return $definition->target_database ?: (string) config('bi_acceleration.clickhouse.database');
    }

    public function qualifiedAggregateTable(AccelerationAggregateDefinition $definition): string
    {
        if ($definition->target_table === null || $definition->target_table === '') {
            throw new InvalidArgumentException('Aggregate target table is missing.');
        }

        return $this->quoteIdentifier($this->database($definition)).'.'.$this->quoteIdentifier($definition->target_table);
    }

    public function qualifiedDetailTable(AccelerationAggregateDefinition $definition): string
    {
        $profile = $definition->detailProfile;
        $database = $profile->target_database ?: (string) config('bi_acceleration.clickhouse.database');

        return $this->quoteIdentifier($database).'.'.$this->quoteIdentifier($profile->target_table);
    }

    private function queryDimensionColumn(AccelerationAggregateDefinition $definition, string $field, ?string $grain, Collection $dimensionColumns, ?AccelerationAggregateColumn $timeColumn): AccelerationAggregateColumn
    {
        if ($definition->time_field === $field && $grain !== null && $definition->time_grain !== 'none') {
            if (! $timeColumn instanceof AccelerationAggregateColumn) {
                throw new InvalidArgumentException("Aggregate time grain column missing for [{$field}].");
            }

            return $timeColumn;
        }

        $column = $dimensionColumns->get($field);

        if (! $column instanceof AccelerationAggregateColumn) {
            throw new InvalidArgumentException("Aggregate dimension column mapping missing for [{$field}].");
        }

        return $column;
    }

    private function queryFilterColumn(AccelerationAggregateDefinition $definition, string $field, Collection $dimensionColumns, ?AccelerationAggregateColumn $timeColumn): AccelerationAggregateColumn
    {
        if ($definition->time_field === $field && $timeColumn instanceof AccelerationAggregateColumn) {
            return $timeColumn;
        }

        $column = $dimensionColumns->get($field);

        if (! $column instanceof AccelerationAggregateColumn) {
            throw new InvalidArgumentException("Aggregate filter column mapping missing for [{$field}].");
        }

        return $column;
    }

    private function rollupExpression(AccelerationAggregateColumn $column): string
    {
        $quoted = $this->quoteIdentifier($column->target_field_name);

        return match ($column->aggregate_function) {
            'sum', 'count' => "sum({$quoted})",
            'min' => "min({$quoted})",
            'max' => "max({$quoted})",
            'avg', 'countDistinct' => "any({$quoted})",
            default => $quoted,
        };
    }

    private function aggregateExpression(string $aggregate, string $detailTargetField): string
    {
        $quoted = $this->quoteIdentifier($detailTargetField);

        return match ($aggregate) {
            'countDistinct' => "countDistinct({$quoted})",
            default => "{$aggregate}({$quoted})",
        };
    }

    private function timeGrainExpression(string $detailTargetField, string $grain): string
    {
        $quoted = $this->quoteIdentifier($detailTargetField);

        return match ($grain) {
            'year' => "toYear({$quoted})",
            'month' => "toStartOfMonth({$quoted})",
            'day' => "toDate({$quoted})",
            default => $quoted,
        };
    }

    private function filterExpression(FilterDTO $filter, string $targetField): string
    {
        $quoted = $this->quoteIdentifier($targetField);

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
            'between' => "{$quoted} between ".$this->literal(((array) $filter->value)[0] ?? null).' and '.$this->literal(((array) $filter->value)[1] ?? null),
            'is_null' => "{$quoted} is null",
            'is_not_null' => "{$quoted} is not null",
            default => throw new InvalidArgumentException("Unsupported filter operator [{$filter->operator}]."),
        };
    }

    /**
     * @param  Collection<string, AccelerationColumn>  $detailColumns
     * @return list<string>
     */
    private function definitionFilterExpressions(AccelerationAggregateDefinition $definition, Collection $detailColumns): array
    {
        return collect($definition->filters_json ?? [])
            ->map(function (array $filter) use ($detailColumns): string {
                $field = (string) ($filter['field'] ?? '');
                $operator = (string) ($filter['operator'] ?? '=');
                $detailColumn = $this->detailColumn($detailColumns, $field);

                return $this->filterExpression(new FilterDTO($field, $operator, $filter['value'] ?? null), $detailColumn->target_field_name);
            })
            ->values()
            ->all();
    }

    private function partitionExpression(AccelerationAggregateDefinition $definition): ?string
    {
        $timeColumn = $definition->columns->firstWhere('column_role', 'time_grain');

        if (! $timeColumn instanceof AccelerationAggregateColumn || ! str_contains($timeColumn->target_type, 'Date')) {
            return null;
        }

        return 'toYYYYMM('.$this->quoteIdentifier($timeColumn->target_field_name).')';
    }

    private function orderExpression(AccelerationAggregateDefinition $definition): string
    {
        $columns = $definition->columns
            ->filter(fn (AccelerationAggregateColumn $column): bool => in_array($column->column_role, ['time_grain', 'dimension'], true))
            ->map(fn (AccelerationAggregateColumn $column): string => $this->quoteIdentifier($column->target_field_name))
            ->values();

        return $columns->isEmpty() ? 'tuple()' : '('.$columns->implode(', ').')';
    }

    /**
     * @param  Collection<string, AccelerationColumn>  $detailColumns
     */
    private function detailColumn(Collection $detailColumns, string $field): AccelerationColumn
    {
        $column = $detailColumns->get($field);

        if (! $column instanceof AccelerationColumn) {
            throw new InvalidArgumentException("Detail column mapping missing for [{$field}].");
        }

        return $column;
    }

    private function metricAggregate(MetricDTO $metric, Collection $fieldsByName): string
    {
        $field = $fieldsByName->get($metric->field);

        return $metric->aggregate ?? ($field?->default_aggregate !== 'none' ? (string) $field?->default_aggregate : 'count');
    }

    private function metricKey(string $field, string $aggregate): string
    {
        return "{$field}|{$aggregate}";
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

    private function quoteIdentifier(string $identifier): string
    {
        if (! IdentifierGuard::isSafe($identifier)) {
            throw new InvalidArgumentException("Unsafe ClickHouse identifier [{$identifier}].");
        }

        return '`'.$identifier.'`';
    }
}
