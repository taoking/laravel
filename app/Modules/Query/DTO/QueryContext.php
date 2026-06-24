<?php

namespace App\Modules\Query\DTO;

class QueryContext
{
    public function __construct(
        public readonly ?int $userId,
        public readonly string $requestSource,
        public readonly int $datasetId,
        public readonly ?int $chartId,
        public readonly ?int $dashboardId,
        public readonly ?int $dataSourceId,
        public readonly ?string $dataSourceType,
        public readonly string $queryMode,
        public readonly bool $semanticLayerUsed,
        public readonly bool $cacheEnabled,
        public readonly bool $accelerationEnabled,
        public readonly bool $permissionEnabled,
        public readonly bool $debugEnabled = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'request_source' => $this->requestSource,
            'dataset_id' => $this->datasetId,
            'chart_id' => $this->chartId,
            'dashboard_id' => $this->dashboardId,
            'data_source_id' => $this->dataSourceId,
            'data_source_type' => $this->dataSourceType,
            'query_mode' => $this->queryMode,
            'semantic_layer_used' => $this->semanticLayerUsed,
            'cache_enabled' => $this->cacheEnabled,
            'acceleration_enabled' => $this->accelerationEnabled,
            'permission_enabled' => $this->permissionEnabled,
            'debug_enabled' => $this->debugEnabled,
        ];
    }
}
