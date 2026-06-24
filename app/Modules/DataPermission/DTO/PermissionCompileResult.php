<?php

namespace App\Modules\DataPermission\DTO;

use App\Modules\Query\DTO\FilterDTO;

class PermissionCompileResult
{
    /**
     * @param  list<FilterDTO>  $rowFilters
     * @param  list<array<string, mixed>>  $columnRules
     * @param  list<string>  $maskedFields
     * @param  list<string>  $hiddenFields
     * @param  list<string>  $requiredPermissionFields
     */
    public function __construct(
        public readonly bool $resourceAllowed,
        public readonly ?string $deniedReason,
        public readonly array $rowFilters,
        public readonly array $columnRules,
        public readonly array $maskedFields,
        public readonly array $hiddenFields,
        public readonly string $permissionHash,
        public readonly array $requiredPermissionFields,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resource_allowed' => $this->resourceAllowed,
            'denied_reason' => $this->deniedReason,
            'row_filters' => array_map(fn (FilterDTO $filter): array => [
                'field' => $filter->field,
                'operator' => $filter->operator,
                'value' => $filter->value,
            ], $this->rowFilters),
            'column_rules' => $this->columnRules,
            'masked_fields' => $this->maskedFields,
            'hidden_fields' => $this->hiddenFields,
            'permission_hash' => $this->permissionHash,
            'required_permission_fields' => $this->requiredPermissionFields,
        ];
    }
}
