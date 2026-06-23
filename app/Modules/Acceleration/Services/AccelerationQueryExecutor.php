<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryExecutionResult;

class AccelerationQueryExecutor
{
    public function __construct(private readonly AccelerationDriverManager $driverManager) {}

    public function compile(LogicalQueryPlan $plan, AccelerationProfile $profile): CompiledQuery
    {
        return $this->driverManager->driver($profile)->generateQuerySql($plan, $profile);
    }

    public function execute(AccelerationProfile $profile, CompiledQuery $query): QueryExecutionResult
    {
        return $this->driverManager->driver($profile)->executeQuery($profile, $query);
    }
}
