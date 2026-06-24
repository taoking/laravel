<?php

namespace App\Modules\Query\Compilers;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\Dialects\SqlDialectManager;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryRequestDTO;

class SqlCompiler
{
    public function __construct(
        private readonly SqlDialectManager $dialectManager,
        private readonly DimensionCompiler $dimensionCompiler,
        private readonly MetricCompiler $metricCompiler,
        private readonly FilterCompiler $filterCompiler,
        private readonly SortCompiler $sortCompiler,
        private readonly PermissionConditionCompiler $permissionConditionCompiler,
    ) {}

    public function compile(Dataset $dataset, QueryRequestDTO $query, ?User $user = null): CompiledQuery
    {
        $dataset->loadMissing(['dataSource', 'fields']);
        $dialect = $this->dialectManager->dialect($dataset->dataSource);
        $fieldsByName = $dataset->fields->keyBy('field_name');
        $selects = [];
        $groupBys = [];
        $wheres = [];
        $bindings = [];
        $columns = [];

        foreach ($query->rawFields as $fieldName) {
            $field = $fieldsByName->get($fieldName);
            $selects[] = $dialect->quoteIdentifier($field->field_name).' as '.$dialect->quoteIdentifier($field->field_name);
            $columns[] = [
                'name' => $field->field_name,
                'label' => $field->display_name,
                'type' => $field->normalized_type,
            ];
        }

        foreach ($query->dimensions as $dimension) {
            $compiled = $this->dimensionCompiler->compile($dimension, $fieldsByName->get($dimension->field), $dialect);
            $selects[] = $compiled['select'];
            $groupBys[] = $compiled['group_by'];
            $columns[] = $compiled['column'];
        }

        foreach ($query->metrics as $metric) {
            $compiled = $this->metricCompiler->compile($metric, $fieldsByName->get($metric->field), $dialect);
            $selects[] = $compiled['select'];
            $columns[] = $compiled['column'];
        }

        foreach ($query->filters as $filter) {
            $compiled = $this->filterCompiler->compile($filter, $fieldsByName->get($filter->field), $dialect);
            $wheres[] = $compiled['sql'];
            $bindings = array_merge($bindings, $compiled['bindings']);
        }

        $permissionConditions = $this->permissionConditionCompiler->compile($dataset, $user, $dialect);
        $wheres = array_merge($wheres, $permissionConditions['conditions']);
        $bindings = array_merge($bindings, $permissionConditions['bindings']);

        $sqlParts = [
            'select '.implode(', ', $selects),
            'from '.$dialect->quoteIdentifier($dataset->main_table),
        ];

        if ($wheres !== []) {
            $sqlParts[] = 'where '.implode(' and ', $wheres);
        }

        if ($groupBys !== []) {
            $sqlParts[] = 'group by '.implode(', ', $groupBys);
        }

        if ($query->sorts !== []) {
            $sqlParts[] = 'order by '.collect($query->sorts)
                ->map(fn ($sort): string => $this->sortCompiler->compile($sort, $dialect))
                ->implode(', ');
        }

        $sqlParts[] = $dialect->compileLimit($query->limit, $query->offset);
        $sql = implode(' ', $sqlParts);

        return new CompiledQuery(
            sql: $sql,
            bindings: $bindings,
            columns: $columns,
            hash: sha1($dataset->id.'|'.$dataset->data_source_id.'|'.$dialect->getName().'|'.$sql.'|'.json_encode($bindings, JSON_THROW_ON_ERROR)),
        );
    }
}
