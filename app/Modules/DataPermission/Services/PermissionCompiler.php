<?php

namespace App\Modules\DataPermission\Services;

use App\Modules\DataPermission\DTO\PermissionCompileResult;
use App\Modules\DataPermission\DTO\PermissionContext;
use App\Modules\DataPermission\Models\ColumnPermissionRule;
use App\Modules\DataPermission\Models\DataPermissionRule;
use App\Modules\Query\DTO\FilterDTO;

class PermissionCompiler
{
    public function __construct(
        private readonly DataPermissionService $dataPermissionService,
        private readonly DataPermissionSubjectResolver $subjectResolver,
    ) {}

    public function compile(PermissionContext $context): PermissionCompileResult
    {
        $dataset = $context->dataset->loadMissing('fields');
        $fieldsByName = $dataset->fields->keyBy('field_name');
        $allowed = $this->resourceAllowed($context);

        if ($allowed !== null) {
            return new PermissionCompileResult(
                resourceAllowed: false,
                deniedReason: $allowed,
                rowFilters: [],
                columnRules: [],
                maskedFields: [],
                hiddenFields: [],
                permissionHash: $this->hash($context, [], []),
                requiredPermissionFields: [],
            );
        }

        $rowRules = $this->dataPermissionService->rowRules($dataset, $context->user);
        $columnRules = $this->dataPermissionService->columnRules($dataset, $context->user);
        $rowFilters = [];
        $requiredPermissionFields = [];

        foreach ($rowRules as $rule) {
            if (! $fieldsByName->has($rule->field_name)) {
                return new PermissionCompileResult(
                    resourceAllowed: false,
                    deniedReason: 'permission_field_missing:'.$rule->field_name,
                    rowFilters: [],
                    columnRules: $this->columnRulesToArray($columnRules),
                    maskedFields: $this->fieldsByType($columnRules, 'masked'),
                    hiddenFields: $this->fieldsByType($columnRules, 'hidden'),
                    permissionHash: $this->hash($context, $rowRules, $columnRules),
                    requiredPermissionFields: [$rule->field_name],
                );
            }

            $rowFilters[] = new FilterDTO($rule->field_name, $rule->operator, $rule->ruleValue($context->user));
            $requiredPermissionFields[] = $rule->field_name;
        }

        return new PermissionCompileResult(
            resourceAllowed: true,
            deniedReason: null,
            rowFilters: $rowFilters,
            columnRules: $this->columnRulesToArray($columnRules),
            maskedFields: $this->fieldsByType($columnRules, 'masked'),
            hiddenFields: $this->fieldsByType($columnRules, 'hidden'),
            permissionHash: $this->hash($context, $rowRules, $columnRules),
            requiredPermissionFields: array_values(array_unique($requiredPermissionFields)),
        );
    }

    private function resourceAllowed(PermissionContext $context): ?string
    {
        $checks = [
            ['dataset', (int) $context->dataset->id],
        ];

        if ($context->chartId !== null) {
            $checks[] = ['chart', $context->chartId];
        }

        if ($context->dashboardId !== null) {
            $checks[] = ['dashboard', $context->dashboardId];
        }

        if ($context->metricId !== null) {
            $checks[] = ['metric', $context->metricId];
        }

        foreach ($checks as [$resourceType, $resourceId]) {
            if (! $this->dataPermissionService->canAccessResource($context->user, $resourceType, $resourceId, 'view')) {
                return "{$resourceType}_not_allowed";
            }
        }

        return null;
    }

    /**
     * @param  list<DataPermissionRule>  $rowRules
     * @param  list<ColumnPermissionRule>  $columnRules
     */
    private function hash(PermissionContext $context, array $rowRules, array $columnRules): string
    {
        return sha1(json_encode([
            'subjects' => $this->subjectResolver->subjects($context->user),
            'dataset_id' => (int) $context->dataset->id,
            'request_source' => $context->requestSource,
            'chart_id' => $context->chartId,
            'dashboard_id' => $context->dashboardId,
            'metric_id' => $context->metricId,
            'row_rules' => array_map(fn (DataPermissionRule $rule): array => [
                'id' => (int) $rule->id,
                'field_name' => $rule->field_name,
                'operator' => $rule->operator,
                'value_type' => $rule->value_type,
                'value' => $rule->ruleValue($context->user),
                'updated_at' => $rule->updated_at?->timestamp,
            ], $rowRules),
            'column_rules' => $this->columnRulesToArray($columnRules),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<ColumnPermissionRule>  $rules
     * @return list<array<string, mixed>>
     */
    private function columnRulesToArray(array $rules): array
    {
        return array_map(fn (ColumnPermissionRule $rule): array => [
            'id' => (int) $rule->id,
            'field_name' => $rule->field_name,
            'permission_type' => $rule->permission_type,
            'updated_at' => $rule->updated_at?->timestamp,
        ], $rules);
    }

    /**
     * @param  list<ColumnPermissionRule>  $rules
     * @return list<string>
     */
    private function fieldsByType(array $rules, string $type): array
    {
        return collect($rules)
            ->filter(fn (ColumnPermissionRule $rule): bool => $rule->permission_type === $type)
            ->pluck('field_name')
            ->unique()
            ->values()
            ->all();
    }
}
