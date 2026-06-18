<?php

namespace App\Modules\Query\Compilers;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryRequestDTO;

class SqlCompiler
{
    public function __construct(
        private readonly SqlIdentifier $identifier,
        private readonly DimensionCompiler $dimensionCompiler,
        private readonly MetricCompiler $metricCompiler,
        private readonly FilterCompiler $filterCompiler,
        private readonly SortCompiler $sortCompiler,
        private readonly PermissionConditionCompiler $permissionConditionCompiler,
    ) {}

    public function compile(Dataset $dataset, QueryRequestDTO $query, ?User $user = null): CompiledQuery
    {
        $dataset->loadMissing('fields');
        $fieldsByName = $dataset->fields->keyBy('field_name');
        $selects = [];
        $groupBys = [];
        $wheres = [];
        $bindings = [];
        $columns = [];

        foreach ($query->dimensions as $dimension) {
            $compiled = $this->dimensionCompiler->compile($dimension, $fieldsByName->get($dimension->field));
            $selects[] = $compiled['select'];
            $groupBys[] = $compiled['group_by'];
            $columns[] = $compiled['column'];
        }

        foreach ($query->metrics as $metric) {
            $compiled = $this->metricCompiler->compile($metric, $fieldsByName->get($metric->field));
            $selects[] = $compiled['select'];
            $columns[] = $compiled['column'];
        }

        foreach ($query->filters as $filter) {
            $compiled = $this->filterCompiler->compile($filter, $fieldsByName->get($filter->field));
            $wheres[] = $compiled['sql'];
            $bindings = array_merge($bindings, $compiled['bindings']);
        }

        $permissionConditions = $this->permissionConditionCompiler->compile($dataset, $user);
        $wheres = array_merge($wheres, $permissionConditions['conditions']);
        $bindings = array_merge($bindings, $permissionConditions['bindings']);

        $sqlParts = [
            'select '.implode(', ', $selects),
            'from '.$this->identifier->quote($dataset->main_table),
        ];

        if ($wheres !== []) {
            $sqlParts[] = 'where '.implode(' and ', $wheres);
        }

        if ($groupBys !== []) {
            $sqlParts[] = 'group by '.implode(', ', $groupBys);
        }

        if ($query->sorts !== []) {
            $sqlParts[] = 'order by '.collect($query->sorts)
                ->map(fn ($sort): string => $this->sortCompiler->compile($sort))
                ->implode(', ');
        }

        $sqlParts[] = "limit {$query->limit} offset {$query->offset}";
        $sql = implode(' ', $sqlParts);

        return new CompiledQuery(
            sql: $sql,
            bindings: $bindings,
            columns: $columns,
            hash: sha1($dataset->id.'|'.$sql.'|'.json_encode($bindings, JSON_THROW_ON_ERROR)),
        );
    }
}
