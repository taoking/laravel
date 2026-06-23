<?php

namespace App\Modules\Acceleration\DTO;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Query\DTO\FilterDTO;
use App\Modules\Query\DTO\QueryRequestDTO;

class LogicalQueryPlan
{
    /**
     * @param  list<FilterDTO>  $permissionFilters
     */
    public function __construct(
        public readonly Dataset $dataset,
        public readonly QueryRequestDTO $query,
        public readonly ?User $user,
        public readonly array $permissionFilters = [],
    ) {}

    public function datasetId(): int
    {
        return (int) $this->dataset->id;
    }
}
