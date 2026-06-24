<?php

namespace App\Modules\Metadata\Services;

use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Models\Metric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class MetadataSyncService
{
    public function __construct(
        private readonly MetadataAssetService $assetService,
        private readonly MetadataLineageService $lineageService,
    ) {}

    /**
     * @return array{asset_type: string, asset_id: int, assets_synced: int, relations_synced: int}
     */
    public function syncAssetMetadata(string $assetType, int $assetId, bool $withLineage = true): array
    {
        $this->assetService->assertAssetType($assetType);

        $assetsSynced = match ($assetType) {
            'data_source' => $this->syncDataSources(false, $assetId)
                + $this->syncPhysicalTables(false, $assetId)
                + $this->syncPhysicalColumns(false, $assetId),
            'physical_table' => $this->syncPhysicalTables(false, null, $assetId),
            'physical_column' => $this->syncPhysicalColumns(false, null, $assetId),
            'dataset' => $this->syncDatasets(false, null, $assetId)
                + $this->syncDatasetFields(false, $assetId)
                + $this->syncDimensions(false, $assetId)
                + $this->syncMetrics(false, $assetId)
                + $this->syncCharts(false, $assetId)
                + $this->syncAccelerationProfiles(false, $assetId)
                + $this->syncAggregateDefinitions(false, $assetId),
            'dataset_field' => $this->syncDatasetFields(false, null, $assetId),
            'dimension' => $this->syncDimensions(false, null, $assetId),
            'metric' => $this->syncMetrics(false, null, $assetId),
            'chart' => $this->syncCharts(false, null, $assetId),
            'dashboard' => $this->syncDashboards(false, $assetId),
            'acceleration_profile' => $this->syncAccelerationProfiles(false, null, $assetId),
            'aggregate_definition' => $this->syncAggregateDefinitions(false, null, $assetId),
            'materialized_view' => $this->syncPhysicalTables(false, null, $assetId),
            default => 0,
        };

        return [
            'asset_type' => $assetType,
            'asset_id' => $assetId,
            'assets_synced' => $assetsSynced,
            'relations_synced' => $withLineage && $assetsSynced > 0
                ? $this->syncLineageForAsset($assetType, $assetId)
                : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sync(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $scope = (string) ($options['scope'] ?? 'all');
        $dataSourceId = $this->nullableInt($options['data_source_id'] ?? $options['data_source'] ?? null);
        $datasetId = $this->nullableInt($options['dataset_id'] ?? $options['dataset'] ?? null);

        if ($dataSourceId !== null) {
            $scope = 'data_source';
        }

        if ($datasetId !== null) {
            $scope = 'dataset';
        }

        $result = [
            'dry_run' => $dryRun,
            'scope' => $scope,
            'assets_synced' => 0,
            'relations_synced' => 0,
            'counts' => [],
        ];

        foreach ($this->assetSyncPlan($scope, $dataSourceId, $datasetId) as $type => $callback) {
            $count = $callback($dryRun);
            $result['counts'][$type] = $count;
            $result['assets_synced'] += $count;
        }

        if (! $dryRun) {
            $lineageResult = $this->rebuildLineage([
                'scope' => $scope,
                'data_source_id' => $dataSourceId,
                'dataset_id' => $datasetId,
                'dry_run' => false,
            ]);
            $result['relations_synced'] = $lineageResult['relations_synced'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function rebuildLineage(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $scope = (string) ($options['scope'] ?? 'all');
        $dataSourceId = $this->nullableInt($options['data_source_id'] ?? $options['data_source'] ?? null);
        $datasetId = $this->nullableInt($options['dataset_id'] ?? $options['dataset'] ?? null);
        $metricId = $this->nullableInt($options['metric_id'] ?? $options['metric'] ?? null);
        $chartId = $this->nullableInt($options['chart_id'] ?? $options['chart'] ?? null);
        $dashboardId = $this->nullableInt($options['dashboard_id'] ?? $options['dashboard'] ?? null);

        if ($dataSourceId !== null) {
            $scope = 'data_source';
        } elseif ($datasetId !== null) {
            $scope = 'dataset';
        } elseif ($metricId !== null) {
            $scope = 'metric';
        } elseif ($chartId !== null) {
            $scope = 'chart';
        } elseif ($dashboardId !== null) {
            $scope = 'dashboard';
        }

        $result = [
            'dry_run' => $dryRun,
            'scope' => $scope,
            'assets_scanned' => 0,
            'relations_synced' => 0,
            'counts' => [],
        ];

        $sync = function (string $assetType, Builder $query) use ($dryRun, &$result): void {
            $models = $query->get();
            $result['counts'][$assetType] = $models->count();
            $result['assets_scanned'] += $models->count();

            if ($dryRun) {
                return;
            }

            foreach ($models as $model) {
                $result['relations_synced'] += $this->lineageService->syncAsset($assetType, (int) $model->id);
            }
        };

        if (in_array($scope, ['all', 'data_source'], true)) {
            $sync('data_source', DataSource::query()->when($dataSourceId, fn ($query) => $query->whereKey($dataSourceId)));
        }

        if (in_array($scope, ['all', 'dataset'], true)) {
            $sync('dataset', Dataset::query()->when($datasetId, fn ($query) => $query->whereKey($datasetId)));
            $sync('dimension', Dimension::query()->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId)));
            $sync('metric', Metric::query()->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId)));
            $sync('chart', Chart::query()->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId)));
            $sync('acceleration_profile', AccelerationProfile::query()->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId)));
            $sync('aggregate_definition', AccelerationAggregateDefinition::query()->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId)));
        }

        if ($scope === 'metrics') {
            $sync('metric', Metric::query());
        }

        if ($scope === 'metric') {
            $sync('metric', Metric::query()->whereKey($metricId));
        }

        if ($scope === 'charts') {
            $sync('chart', Chart::query());
        }

        if ($scope === 'chart') {
            $sync('chart', Chart::query()->whereKey($chartId));
        }

        if (in_array($scope, ['all', 'dashboards'], true)) {
            $sync('dashboard', Dashboard::query());
        }

        if ($scope === 'dashboard') {
            $sync('dashboard', Dashboard::query()->whereKey($dashboardId));
        }

        return $result;
    }

    /**
     * @return array<string, callable(bool): int>
     */
    private function assetSyncPlan(string $scope, ?int $dataSourceId, ?int $datasetId): array
    {
        $plan = [];

        if (in_array($scope, ['all', 'data_source'], true)) {
            $plan['data_sources'] = fn (bool $dryRun): int => $this->syncDataSources($dryRun, $dataSourceId);
            $plan['physical_tables'] = fn (bool $dryRun): int => $this->syncPhysicalTables($dryRun, $dataSourceId);
            $plan['physical_columns'] = fn (bool $dryRun): int => $this->syncPhysicalColumns($dryRun, $dataSourceId);
        }

        if (in_array($scope, ['all', 'dataset'], true)) {
            $plan['datasets'] = fn (bool $dryRun): int => $this->syncDatasets($dryRun, $dataSourceId, $datasetId);
            $plan['dataset_fields'] = fn (bool $dryRun): int => $this->syncDatasetFields($dryRun, $datasetId);
            $plan['dimensions'] = fn (bool $dryRun): int => $this->syncDimensions($dryRun, $datasetId);
            $plan['metrics'] = fn (bool $dryRun): int => $this->syncMetrics($dryRun, $datasetId);
            $plan['charts'] = fn (bool $dryRun): int => $this->syncCharts($dryRun, $datasetId);
            $plan['dashboards'] = fn (bool $dryRun): int => $this->syncDashboards($dryRun);
            $plan['acceleration_profiles'] = fn (bool $dryRun): int => $this->syncAccelerationProfiles($dryRun, $datasetId);
            $plan['aggregate_definitions'] = fn (bool $dryRun): int => $this->syncAggregateDefinitions($dryRun, $datasetId);
        }

        if ($scope === 'metrics') {
            $plan['metrics'] = fn (bool $dryRun): int => $this->syncMetrics($dryRun, null);
        }

        if ($scope === 'charts') {
            $plan['charts'] = fn (bool $dryRun): int => $this->syncCharts($dryRun, null);
        }

        if ($scope === 'dashboards') {
            $plan['dashboards'] = fn (bool $dryRun): int => $this->syncDashboards($dryRun);
        }

        return $plan;
    }

    private function syncDataSources(bool $dryRun, ?int $dataSourceId): int
    {
        $sources = DataSource::query()->when($dataSourceId, fn ($query) => $query->whereKey($dataSourceId))->get();

        if ($dryRun) {
            return $sources->count();
        }

        $sources->each(fn (DataSource $source) => $this->assetService->register('data_source', (int) $source->id, [
            'name' => $source->name,
            'code' => $source->type,
            'description' => null,
            'status' => $source->status ?? 'active',
            'owner_id' => $source->created_by,
            'properties_json' => [
                'type' => $source->type,
                'status' => $source->status,
                'last_tested_at' => $source->last_tested_at?->toISOString(),
            ],
        ]));

        return $sources->count();
    }

    private function syncPhysicalTables(bool $dryRun, ?int $dataSourceId, ?int $tableId = null): int
    {
        $tables = DataSourceTable::query()
            ->when($dataSourceId, fn ($query) => $query->where('data_source_id', $dataSourceId))
            ->when($tableId, fn ($query) => $query->whereKey($tableId))
            ->get();

        if ($dryRun) {
            return $tables->count();
        }

        $tables->each(function (DataSourceTable $table): void {
            $payload = [
                'name' => $table->table_name,
                'code' => $table->table_name,
                'description' => $table->table_comment,
                'data_source_id' => $table->data_source_id,
                'status' => 'active',
                'properties_json' => [
                    'table_type' => $table->table_type,
                    'row_count_estimate' => $table->row_count_estimate,
                    'synced_at' => $table->synced_at?->toISOString(),
                ],
            ];

            $this->assetService->register('physical_table', (int) $table->id, $payload);

            if (str_contains(strtolower((string) $table->table_type), 'materialized')) {
                $this->assetService->register('materialized_view', (int) $table->id, $payload);
            }
        });

        return $tables->count();
    }

    private function syncPhysicalColumns(bool $dryRun, ?int $dataSourceId, ?int $fieldId = null): int
    {
        $columns = DataSourceField::query()
            ->when($dataSourceId, fn ($query) => $query->where('data_source_id', $dataSourceId))
            ->when($fieldId, fn ($query) => $query->whereKey($fieldId))
            ->get();

        if ($dryRun) {
            return $columns->count();
        }

        $columns->each(fn (DataSourceField $field) => $this->assetService->register('physical_column', (int) $field->id, [
            'name' => $field->field_name,
            'code' => $field->field_name,
            'description' => $field->field_comment,
            'data_source_id' => $field->data_source_id,
            'status' => 'active',
            'properties_json' => [
                'table_name' => $field->table_name,
                'data_type' => $field->data_type,
                'normalized_type' => $field->normalized_type,
                'is_nullable' => $field->is_nullable,
                'is_primary_key' => $field->is_primary_key,
                'ordinal_position' => $field->ordinal_position,
            ],
        ]));

        return $columns->count();
    }

    private function syncDatasets(bool $dryRun, ?int $dataSourceId, ?int $datasetId): int
    {
        $datasets = Dataset::query()
            ->when($dataSourceId, fn ($query) => $query->where('data_source_id', $dataSourceId))
            ->when($datasetId, fn ($query) => $query->whereKey($datasetId))
            ->get();

        if ($dryRun) {
            return $datasets->count();
        }

        $datasets->each(fn (Dataset $dataset) => $this->assetService->register('dataset', (int) $dataset->id, [
            'name' => $dataset->name,
            'code' => $dataset->main_table,
            'description' => $dataset->description,
            'data_source_id' => $dataset->data_source_id,
            'dataset_id' => $dataset->id,
            'status' => $dataset->status ?? 'active',
            'owner_id' => $dataset->created_by,
            'properties_json' => [
                'dataset_type' => $dataset->dataset_type,
                'main_table' => $dataset->main_table,
            ],
        ]));

        return $datasets->count();
    }

    private function syncDatasetFields(bool $dryRun, ?int $datasetId, ?int $fieldId = null): int
    {
        $fields = DatasetField::query()
            ->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId))
            ->when($fieldId, fn ($query) => $query->whereKey($fieldId))
            ->get();

        if ($dryRun) {
            return $fields->count();
        }

        $fields->each(function (DatasetField $field): void {
            $field->loadMissing('dataset');
            $this->assetService->register('dataset_field', (int) $field->id, [
                'name' => $field->display_name ?: $field->field_alias ?: $field->field_name,
                'code' => $field->field_alias ?: $field->field_name,
                'description' => null,
                'data_source_id' => $field->dataset?->data_source_id,
                'dataset_id' => $field->dataset_id,
                'status' => $field->is_visible ? 'active' : 'disabled',
                'properties_json' => Arr::only($field->toArray(), [
                    'table_name',
                    'field_name',
                    'field_alias',
                    'source_type',
                    'normalized_type',
                    'semantic_type',
                    'is_dimension',
                    'is_metric',
                    'is_filterable',
                    'default_aggregate',
                ]),
            ]);
        });

        return $fields->count();
    }

    private function syncDimensions(bool $dryRun, ?int $datasetId, ?int $dimensionId = null): int
    {
        $dimensions = Dimension::query()
            ->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId))
            ->when($dimensionId, fn ($query) => $query->whereKey($dimensionId))
            ->get();

        if ($dryRun) {
            return $dimensions->count();
        }

        $dimensions->each(fn (Dimension $dimension) => $this->assetService->register('dimension', (int) $dimension->id, [
            'name' => $dimension->name,
            'code' => $dimension->code,
            'description' => $dimension->description,
            'dataset_id' => $dimension->dataset_id,
            'status' => $dimension->status,
            'owner_id' => $dimension->created_by,
            'properties_json' => [
                'dimension_type' => $dimension->dimension_type,
                'field_name' => $dimension->field_name,
                'time_grain_options_json' => $dimension->time_grain_options_json,
            ],
        ]));

        return $dimensions->count();
    }

    private function syncMetrics(bool $dryRun, ?int $datasetId, ?int $metricId = null): int
    {
        $metrics = Metric::query()
            ->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId))
            ->when($metricId, fn ($query) => $query->whereKey($metricId))
            ->get();

        if ($dryRun) {
            return $metrics->count();
        }

        $metrics->each(fn (Metric $metric) => $this->assetService->register('metric', (int) $metric->id, [
            'name' => $metric->name,
            'code' => $metric->code,
            'description' => $metric->description,
            'dataset_id' => $metric->dataset_id,
            'status' => $metric->status,
            'owner_id' => $metric->owner_id,
            'properties_json' => [
                'metric_type' => $metric->metric_type,
                'aggregate_function' => $metric->aggregate_function,
                'source_field' => $metric->source_field,
                'formula' => $metric->formula,
                'unit' => $metric->unit,
                'version' => $metric->version,
            ],
        ]));

        return $metrics->count();
    }

    private function syncCharts(bool $dryRun, ?int $datasetId, ?int $chartId = null): int
    {
        $charts = Chart::query()
            ->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId))
            ->when($chartId, fn ($query) => $query->whereKey($chartId))
            ->get();

        if ($dryRun) {
            return $charts->count();
        }

        $charts->each(fn (Chart $chart) => $this->assetService->register('chart', (int) $chart->id, [
            'name' => $chart->name,
            'code' => null,
            'description' => $chart->description,
            'dataset_id' => $chart->dataset_id,
            'status' => $chart->status,
            'owner_id' => $chart->created_by,
            'properties_json' => [
                'chart_type' => $chart->chart_type,
            ],
        ]));

        return $charts->count();
    }

    private function syncDashboards(bool $dryRun, ?int $dashboardId = null): int
    {
        $dashboards = Dashboard::query()
            ->when($dashboardId, fn ($query) => $query->whereKey($dashboardId))
            ->get();

        if ($dryRun) {
            return $dashboards->count();
        }

        $dashboards->each(fn (Dashboard $dashboard) => $this->assetService->register('dashboard', (int) $dashboard->id, [
            'name' => $dashboard->name,
            'code' => null,
            'description' => $dashboard->description,
            'status' => $dashboard->status,
            'owner_id' => $dashboard->created_by,
            'properties_json' => [
                'widget_count' => $dashboard->widgets()->count(),
            ],
        ]));

        return $dashboards->count();
    }

    private function syncAccelerationProfiles(bool $dryRun, ?int $datasetId, ?int $profileId = null): int
    {
        $profiles = AccelerationProfile::query()
            ->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId))
            ->when($profileId, fn ($query) => $query->whereKey($profileId))
            ->get();

        if ($dryRun) {
            return $profiles->count();
        }

        $profiles->each(fn (AccelerationProfile $profile) => $this->assetService->register('acceleration_profile', (int) $profile->id, [
            'name' => $profile->name,
            'code' => $profile->target_table,
            'dataset_id' => $profile->dataset_id,
            'status' => $profile->status,
            'properties_json' => [
                'engine_type' => $profile->engine_type,
                'mode' => $profile->mode,
                'target_table' => $profile->target_table,
                'refresh_type' => $profile->refresh_type,
                'row_count' => $profile->row_count,
                'version' => $profile->version,
            ],
        ]));

        return $profiles->count();
    }

    private function syncAggregateDefinitions(bool $dryRun, ?int $datasetId, ?int $definitionId = null): int
    {
        $definitions = AccelerationAggregateDefinition::query()
            ->when($datasetId, fn ($query) => $query->where('dataset_id', $datasetId))
            ->when($definitionId, fn ($query) => $query->whereKey($definitionId))
            ->get();

        if ($dryRun) {
            return $definitions->count();
        }

        $definitions->each(fn (AccelerationAggregateDefinition $definition) => $this->assetService->register('aggregate_definition', (int) $definition->id, [
            'name' => $definition->name,
            'code' => $definition->target_table,
            'dataset_id' => $definition->dataset_id,
            'status' => $definition->status,
            'owner_id' => $definition->created_by,
            'properties_json' => [
                'target_table' => $definition->target_table,
                'time_field' => $definition->time_field,
                'time_grain' => $definition->time_grain,
                'row_count' => $definition->row_count,
                'version' => $definition->version,
            ],
        ]));

        return $definitions->count();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return (int) $value;
    }

    private function syncLineageForAsset(string $assetType, int $assetId): int
    {
        if ($assetType === 'dataset_field') {
            $datasetId = DatasetField::query()->whereKey($assetId)->value('dataset_id');

            return $datasetId !== null ? $this->lineageService->syncAsset('dataset', (int) $datasetId) : 0;
        }

        if (in_array($assetType, ['physical_table', 'materialized_view'], true)) {
            $dataSourceId = DataSourceTable::query()->whereKey($assetId)->value('data_source_id');

            return $dataSourceId !== null ? $this->lineageService->syncAsset('data_source', (int) $dataSourceId) : 0;
        }

        if ($assetType === 'physical_column') {
            $dataSourceId = DataSourceField::query()->whereKey($assetId)->value('data_source_id');

            return $dataSourceId !== null ? $this->lineageService->syncAsset('data_source', (int) $dataSourceId) : 0;
        }

        return $this->lineageService->syncAsset($assetType, $assetId);
    }
}
