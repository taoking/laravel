<?php

namespace App\Modules\Query\Validators;

use App\Models\User;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Services\IdentifierGuard;
use App\Modules\Query\DTO\QueryRequestDTO;
use Illuminate\Validation\ValidationException;

class QueryRequestValidator
{
    private const AGGREGATES = ['sum', 'avg', 'count', 'max', 'min'];

    private const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'like', 'not_like', 'between', 'is_null', 'is_not_null'];

    private const TIME_GRANULARITIES = ['year', 'quarter', 'month', 'week', 'day', 'hour', 'minute'];

    public function __construct(
        private readonly FieldPermissionValidator $fieldPermissionValidator,
        private readonly DataPermissionService $dataPermissionService,
    ) {}

    public function validate(Dataset $dataset, QueryRequestDTO $query, ?User $user = null): void
    {
        if ($dataset->status !== 'active') {
            throw ValidationException::withMessages([
                'dataset_id' => ['The selected dataset is disabled.'],
            ]);
        }

        if ($dataset->dataset_type !== 'single_table') {
            throw ValidationException::withMessages([
                'dataset_id' => ['Only single table datasets are supported.'],
            ]);
        }

        if (! IdentifierGuard::isSafe($dataset->main_table)) {
            throw ValidationException::withMessages([
                'dataset_id' => ['The dataset table name is not allowed.'],
            ]);
        }

        if ($query->dimensions === [] && $query->metrics === []) {
            throw ValidationException::withMessages([
                'metrics' => ['At least one dimension or metric is required.'],
            ]);
        }

        $fieldsByName = $dataset->fields->keyBy('field_name');
        $hiddenFields = $this->dataPermissionService->hiddenFields($dataset, $user);
        $selectAliases = [];

        foreach ($query->dimensions as $dimension) {
            $field = $this->fieldPermissionValidator->assertFieldExists($dataset, $fieldsByName, $dimension->field);
            $this->assertFieldNotHidden($hiddenFields, $dimension->field, 'dimensions');

            if (! $field->is_dimension || ! $field->is_visible) {
                throw ValidationException::withMessages([
                    'dimensions' => ["Field [{$dimension->field}] is not available as a dimension."],
                ]);
            }

            if ($dimension->timeGranularity !== null && ! in_array($dimension->timeGranularity, self::TIME_GRANULARITIES, true)) {
                throw ValidationException::withMessages([
                    'dimensions' => ["Time granularity [{$dimension->timeGranularity}] is not supported."],
                ]);
            }

            $this->assertAlias($dimension->alias ?? $dimension->field, 'dimensions');
            $selectAliases[] = $dimension->alias ?? $dimension->field;
        }

        foreach ($query->metrics as $metric) {
            $field = $this->fieldPermissionValidator->assertFieldExists($dataset, $fieldsByName, $metric->field);
            $this->assertFieldNotHidden($hiddenFields, $metric->field, 'metrics');

            if (! $field->is_metric || ! $field->is_visible) {
                throw ValidationException::withMessages([
                    'metrics' => ["Field [{$metric->field}] is not available as a metric."],
                ]);
            }

            $aggregate = $metric->aggregate ?? ($field->default_aggregate !== 'none' ? $field->default_aggregate : 'count');

            if (! in_array($aggregate, self::AGGREGATES, true)) {
                throw ValidationException::withMessages([
                    'metrics' => ["Aggregate [{$aggregate}] is not supported."],
                ]);
            }

            $this->assertAlias($metric->alias ?? "{$metric->field}_{$aggregate}", 'metrics');
            $selectAliases[] = $metric->alias ?? "{$metric->field}_{$aggregate}";
        }

        foreach ($query->filters as $filter) {
            $field = $this->fieldPermissionValidator->assertFieldExists($dataset, $fieldsByName, $filter->field);
            $this->assertFieldNotHidden($hiddenFields, $filter->field, 'filters');

            if (! $field->is_filterable) {
                throw ValidationException::withMessages([
                    'filters' => ["Field [{$filter->field}] is not filterable."],
                ]);
            }

            if (! in_array($filter->operator, self::OPERATORS, true)) {
                throw ValidationException::withMessages([
                    'filters' => ["Operator [{$filter->operator}] is not supported."],
                ]);
            }

            $this->validateFilterValue($filter->operator, $filter->value);
        }

        foreach ($query->sorts as $sort) {
            if (! in_array($sort->direction, ['asc', 'desc'], true)) {
                throw ValidationException::withMessages([
                    'sorts' => ['Sort direction must be asc or desc.'],
                ]);
            }

            if (! in_array($sort->field, $selectAliases, true)) {
                throw ValidationException::withMessages([
                    'sorts' => ["Sort field [{$sort->field}] must be selected by dimensions or metrics."],
                ]);
            }
        }
    }

    private function assertAlias(string $alias, string $key): void
    {
        if (! IdentifierGuard::isSafe($alias)) {
            throw ValidationException::withMessages([
                $key => ["Alias [{$alias}] is not allowed."],
            ]);
        }
    }

    /**
     * @param  list<string>  $hiddenFields
     */
    private function assertFieldNotHidden(array $hiddenFields, string $fieldName, string $key): void
    {
        if (in_array($fieldName, $hiddenFields, true)) {
            throw ValidationException::withMessages([
                $key => ["Field [{$fieldName}] is hidden by column permissions."],
            ]);
        }
    }

    private function validateFilterValue(string $operator, mixed $value): void
    {
        if (in_array($operator, ['is_null', 'is_not_null'], true)) {
            return;
        }

        if (in_array($operator, ['in', 'not_in'], true) && (! is_array($value) || $value === [])) {
            throw ValidationException::withMessages([
                'filters' => ['The in/not_in operators require a non-empty array value.'],
            ]);
        }

        if ($operator === 'between' && (! is_array($value) || count($value) !== 2)) {
            throw ValidationException::withMessages([
                'filters' => ['The between operator requires exactly two values.'],
            ]);
        }

        if (! in_array($operator, ['in', 'not_in', 'between'], true) && $value === null) {
            throw ValidationException::withMessages([
                'filters' => ['The selected operator requires a value.'],
            ]);
        }
    }
}
