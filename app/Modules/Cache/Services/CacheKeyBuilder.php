<?php

namespace App\Modules\Cache\Services;

class CacheKeyBuilder
{
    public function dataSourceTables(int $dataSourceId): string
    {
        return "bi:data_source:{$dataSourceId}:tables";
    }

    public function dataSourceDatabases(int $dataSourceId): string
    {
        return "bi:data_source:{$dataSourceId}:databases";
    }

    public function dataSourceViews(int $dataSourceId): string
    {
        return "bi:data_source:{$dataSourceId}:views";
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

    public function chartQuery(int $chartId, string $queryHash, string $scope, bool $accelerationHit = false, ?int $profileId = null, int $version = 0, ?int $aggregateDefinitionId = null, ?string $engineType = null, ?int $dataSourceId = null, ?string $metricVersionsHash = null, ?string $permissionHash = null, string $queryMode = 'raw_field', ?string $accelerationMode = null): string
    {
        $acceleration = $this->accelerationSegment($accelerationHit, $profileId, $version, $aggregateDefinitionId, $accelerationMode);
        $source = $this->sourceSegment($engineType, $dataSourceId);
        $semantic = $this->semanticSegment($metricVersionsHash);
        $permission = $this->permissionSegment($permissionHash);
        $mode = $this->modeSegment($queryMode);

        return "bi:chart:{$chartId}:query:{$scope}:{$mode}:{$permission}:{$semantic}:{$source}:{$acceleration}:{$queryHash}";
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

    public function query(string $queryHash, string $scope, bool $accelerationHit = false, ?int $profileId = null, int $version = 0, ?int $aggregateDefinitionId = null, ?string $engineType = null, ?int $dataSourceId = null, ?string $metricVersionsHash = null, ?string $permissionHash = null, string $queryMode = 'raw_field', ?string $accelerationMode = null): string
    {
        $acceleration = $this->accelerationSegment($accelerationHit, $profileId, $version, $aggregateDefinitionId, $accelerationMode);
        $source = $this->sourceSegment($engineType, $dataSourceId);
        $semantic = $this->semanticSegment($metricVersionsHash);
        $permission = $this->permissionSegment($permissionHash);
        $mode = $this->modeSegment($queryMode);

        return "bi:query:{$scope}:{$mode}:{$permission}:{$semantic}:{$source}:{$acceleration}:{$queryHash}";
    }

    private function semanticSegment(?string $metricVersionsHash): string
    {
        return 'semantic:'.($metricVersionsHash !== null && $metricVersionsHash !== '' ? $metricVersionsHash : 'none');
    }

    private function sourceSegment(?string $engineType, ?int $dataSourceId): string
    {
        $engine = $engineType !== null && $engineType !== '' ? $engineType : 'unknown';
        $source = $dataSourceId !== null ? (string) $dataSourceId : 'none';

        return "engine:{$engine}:ds:{$source}";
    }

    private function permissionSegment(?string $permissionHash): string
    {
        return 'perm:'.($permissionHash !== null && $permissionHash !== '' ? $permissionHash : 'none');
    }

    private function modeSegment(string $queryMode): string
    {
        return 'mode:'.$queryMode;
    }

    private function accelerationSegment(bool $hit, ?int $profileId, int $version, ?int $aggregateDefinitionId = null, ?string $accelerationMode = null): string
    {
        if ($hit && $aggregateDefinitionId !== null) {
            return "acc:aggregate_table:agg:{$aggregateDefinitionId}:v:{$version}";
        }

        $state = $accelerationMode !== null && $accelerationMode !== '' ? $accelerationMode : ($hit ? 'hit' : 'raw');
        $profile = $profileId !== null ? (string) $profileId : 'none';

        return "acc:{$state}:profile:{$profile}:v:{$version}";
    }
}
