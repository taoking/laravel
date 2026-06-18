<?php

namespace App\Modules\Query\Compilers;

use App\Models\User;
use App\Modules\DataPermission\Services\DataPermissionService;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\FilterDTO;

class PermissionConditionCompiler
{
    public function __construct(
        private readonly DataPermissionService $dataPermissionService,
        private readonly FilterCompiler $filterCompiler,
    ) {}

    /**
     * @return array{conditions: list<string>, bindings: list<mixed>}
     */
    public function compile(Dataset $dataset, ?User $user): array
    {
        $fieldsByName = $dataset->fields->keyBy('field_name');
        $conditions = [];
        $bindings = [];

        foreach ($this->dataPermissionService->rowRules($dataset, $user) as $rule) {
            $field = $fieldsByName->get($rule->field_name);

            if ($field === null) {
                continue;
            }

            $compiled = $this->filterCompiler->compile(
                new FilterDTO($rule->field_name, $rule->operator, $rule->ruleValue()),
                $field,
            );
            $conditions[] = $compiled['sql'];
            $bindings = array_merge($bindings, $compiled['bindings']);
        }

        return [
            'conditions' => $conditions,
            'bindings' => $bindings,
        ];
    }
}
