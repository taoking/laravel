<?php

namespace App\Modules\Cache\Services;

class CacheKeyBuilder
{
    public function dataSourceTables(int $dataSourceId): string
    {
        return "bi:data_source:{$dataSourceId}:tables";
    }

    public function dataSourceFields(int $dataSourceId, string $tableName): string
    {
        return "bi:data_source:{$dataSourceId}:table:{$tableName}:fields";
    }

    public function datasetSchema(int $datasetId): string
    {
        return "bi:dataset:{$datasetId}:schema";
    }

    public function chartConfig(int $chartId): string
    {
        return "bi:chart:{$chartId}:config";
    }

    public function chartQuery(int $chartId, string $queryHash, string $scope): string
    {
        return "bi:chart:{$chartId}:query:{$scope}:{$queryHash}";
    }

    public function chartQueryIndex(int $chartId): string
    {
        return "bi:chart:{$chartId}:query_keys";
    }

    public function dashboardLayout(int $dashboardId): string
    {
        return "bi:dashboard:{$dashboardId}:layout";
    }

    public function userPermissions(int $userId): string
    {
        return "bi:user:{$userId}:permissions";
    }

    public function userDataPermissions(int $userId): string
    {
        return "bi:user:{$userId}:data_permissions";
    }

    public function query(string $queryHash, string $scope): string
    {
        return "bi:query:{$scope}:{$queryHash}";
    }
}
