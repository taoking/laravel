<?php

namespace App\Modules\Acceleration\Drivers;

use App\Modules\Acceleration\DTO\LogicalQueryPlan;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryExecutionResult;

interface AccelerationDriverInterface
{
    public function testConnection(): bool;

    public function createDatabaseIfNotExists(string $database): void;

    public function tableExists(AccelerationProfile $profile): bool;

    public function createDetailTable(AccelerationProfile $profile): void;

    public function dropTable(AccelerationProfile $profile): void;

    /**
     * @param  iterable<array<string, mixed>|object>  $rows
     */
    public function insertRows(AccelerationProfile $profile, iterable $rows): int;

    public function generateQuerySql(LogicalQueryPlan $plan, AccelerationProfile $profile): CompiledQuery;

    public function executeQuery(AccelerationProfile $profile, CompiledQuery $query): QueryExecutionResult;

    public function supports(LogicalQueryPlan $plan, AccelerationProfile $profile): bool;
}
