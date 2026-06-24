<?php

namespace App\Modules\Metadata\Services;

use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use App\Modules\Metadata\Models\MetadataAsset;
use App\Modules\Metadata\Models\MetadataLineageRelation;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Services\MetricFormulaParser;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MetadataLineageService
{
    public function __construct(
        private readonly MetadataAssetService $assetService,
        private readonly MetricFormulaParser $formulaParser,
    ) {}

    public function syncAsset(string $assetType, int $assetId): int
    {
        $this->assetService->assertAssetType($assetType);

        return match ($assetType) {
            'data_source' => $this->syncDataSourceLineage(DataSource::query()->findOrFail($assetId)),
            'dataset' => $this->syncDatasetLineage(Dataset::query()->findOrFail($assetId)),
            'dimension' => $this->syncDimensionLineage(Dimension::query()->findOrFail($assetId)),
            'metric' => $this->syncMetricLineage(Metric::query()->findOrFail($assetId)),
            'chart' => $this->syncChartLineage(Chart::query()->findOrFail($assetId)),
            'dashboard' => $this->syncDashboardLineage(Dashboard::query()->findOrFail($assetId)),
            'acceleration_profile' => $this->syncAccelerationProfileLineage(AccelerationProfile::query()->findOrFail($assetId)),
            'aggregate_definition' => $this->syncAggregateDefinitionLineage(AccelerationAggregateDefinition::query()->findOrFail($assetId)),
            default => 0,
        };
    }

    public function syncDataSourceLineage(DataSource $dataSource): int
    {
        $this->forgetSource('data_source', (int) $dataSource->id);
        $count = 0;

        DataSourceTable::query()
            ->where('data_source_id', $dataSource->id)
            ->get()
            ->each(function (DataSourceTable $table) use ($dataSource, &$count): void {
                $this->relation('data_source', (int) $dataSource->id, 'physical_table', (int) $table->id, 'contains', [
                    'table_name' => $table->table_name,
                ]);
                $count++;
                $this->forgetSource('physical_table', (int) $table->id);

                DataSourceField::query()
                    ->where('table_id', $table->id)
                    ->get()
                    ->each(function (DataSourceField $field) use ($table, &$count): void {
                        $this->relation('physical_table', (int) $table->id, 'physical_column', (int) $field->id, 'contains', [
                            'field_name' => $field->field_name,
                        ]);
                        $count++;
                    });
            });

        return $count;
    }

    public function syncDatasetLineage(Dataset $dataset): int
    {
        $dataset->loadMissing(['tables', 'fields']);
        $this->forgetSource('dataset', (int) $dataset->id);
        $count = 0;

        $tables = $dataset->tables;

        if ($tables->isEmpty() && $dataset->main_table !== '') {
            $physicalTable = DataSourceTable::query()
                ->where('data_source_id', $dataset->data_source_id)
                ->where('table_name', $dataset->main_table)
                ->first();

            if ($physicalTable instanceof DataSourceTable) {
                $this->relation('dataset', (int) $dataset->id, 'physical_table', (int) $physicalTable->id, 'depends_on');
                $count++;
            } else {
                $this->relation('dataset', (int) $dataset->id, 'data_source', (int) $dataset->data_source_id, 'depends_on', [
                    'reason' => 'physical table metadata not found',
                ], 'medium');
                $count++;
            }
        }

        $tables->each(function ($table) use ($dataset, &$count): void {
            $physicalTable = DataSourceTable::query()
                ->where('data_source_id', $table->data_source_id)
                ->where('table_name', $table->table_name)
                ->first();

            if ($physicalTable instanceof DataSourceTable) {
                $this->relation('dataset', (int) $dataset->id, 'physical_table', (int) $physicalTable->id, 'depends_on', [
                    'table_name' => $table->table_name,
                    'alias' => $table->alias,
                ]);
                $count++;
            }
        });

        $dataset->fields->each(function (DatasetField $field) use ($dataset, &$count): void {
            $this->forgetSource('dataset_field', (int) $field->id);
            $this->relation('dataset', (int) $dataset->id, 'dataset_field', (int) $field->id, 'contains', [
                'field_name' => $field->field_name,
            ]);
            $count++;

            $physicalColumn = DataSourceField::query()
                ->where('data_source_id', $dataset->data_source_id)
                ->where('table_name', $field->table_name)
                ->where('field_name', $field->field_name)
                ->first();

            if ($physicalColumn instanceof DataSourceField) {
                $this->relation('dataset_field', (int) $field->id, 'physical_column', (int) $physicalColumn->id, 'maps_to', [
                    'table_name' => $field->table_name,
                    'field_name' => $field->field_name,
                ]);
                $count++;
            } elseif ($field->source_type !== 'physical') {
                $this->relation('dataset_field', (int) $field->id, 'dataset', (int) $dataset->id, 'derived_from', [
                    'expression' => $field->expression,
                ], 'medium');
                $count++;
            }
        });

        return $count;
    }

    public function syncDimensionLineage(Dimension $dimension): int
    {
        $this->forgetSource('dimension', (int) $dimension->id);
        $field = DatasetField::query()
            ->where('dataset_id', $dimension->dataset_id)
            ->where('field_name', $dimension->field_name)
            ->first();

        if (! $field instanceof DatasetField) {
            return 0;
        }

        $this->relation('dimension', (int) $dimension->id, 'dataset_field', (int) $field->id, 'depends_on', [
            'field_name' => $dimension->field_name,
        ]);

        return 1;
    }

    public function syncMetricLineage(Metric $metric): int
    {
        $metric->loadMissing('dependencies.dependsOnMetric');
        $this->forgetSource('metric', (int) $metric->id);
        $count = 0;

        if ($metric->metric_type === 'base' && $metric->source_field !== null) {
            $field = DatasetField::query()
                ->where('dataset_id', $metric->dataset_id)
                ->where('field_name', $metric->source_field)
                ->first();

            if ($field instanceof DatasetField) {
                $this->relation('metric', (int) $metric->id, 'dataset_field', (int) $field->id, 'depends_on', [
                    'field_name' => $metric->source_field,
                    'aggregate_function' => $metric->aggregate_function,
                ]);
                $count++;
            }
        }

        foreach ($metric->dependencies as $dependency) {
            if ($dependency->dependency_type === 'metric' && $dependency->depends_on_metric_id !== null) {
                $this->relation('metric', (int) $metric->id, 'metric', (int) $dependency->depends_on_metric_id, 'depends_on');
                $count++;
            }

            if ($dependency->dependency_type === 'field' && $dependency->depends_on_field_name !== null) {
                $field = DatasetField::query()
                    ->where('dataset_id', $metric->dataset_id)
                    ->where('field_name', $dependency->depends_on_field_name)
                    ->first();

                if ($field instanceof DatasetField) {
                    $this->relation('metric', (int) $metric->id, 'dataset_field', (int) $field->id, 'depends_on', [
                        'field_name' => $dependency->depends_on_field_name,
                    ]);
                    $count++;
                }
            }
        }

        if ($metric->metric_type !== 'base' && is_string($metric->formula)) {
            foreach ($this->formulaParser->dependencies($metric->formula) as $code) {
                $dependencyMetric = Metric::query()
                    ->where('dataset_id', $metric->dataset_id)
                    ->where('code', $code)
                    ->first();

                if ($dependencyMetric instanceof Metric) {
                    $this->relation('metric', (int) $metric->id, 'metric', (int) $dependencyMetric->id, 'depends_on', [
                        'formula' => $metric->formula,
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function syncChartLineage(Chart $chart): int
    {
        $chart->loadMissing('dataset.fields');
        $this->forgetSource('chart', (int) $chart->id);
        $count = 0;

        $this->relation('chart', (int) $chart->id, 'dataset', (int) $chart->dataset_id, 'uses');
        $count++;

        foreach ($this->metricCodesFromConfig($chart->config_json ?? []) as $code) {
            $metric = Metric::query()
                ->where('dataset_id', $chart->dataset_id)
                ->where('code', $code)
                ->first();

            if ($metric instanceof Metric) {
                $this->relation('chart', (int) $chart->id, 'metric', (int) $metric->id, 'uses', [
                    'metric_code' => $code,
                ]);
                $count++;
            }
        }

        foreach ($this->dimensionCodesFromConfig($chart->config_json ?? []) as $code) {
            $dimension = Dimension::query()
                ->where('dataset_id', $chart->dataset_id)
                ->where('code', $code)
                ->first();

            if ($dimension instanceof Dimension) {
                $this->relation('chart', (int) $chart->id, 'dimension', (int) $dimension->id, 'uses', [
                    'dimension_code' => $code,
                ]);
                $count++;
            }
        }

        foreach ($this->fieldNamesFromChartConfig($chart->config_json ?? []) as $fieldName) {
            $field = $chart->dataset?->fields->firstWhere('field_name', $fieldName);

            if ($field instanceof DatasetField) {
                $this->relation('chart', (int) $chart->id, 'dataset_field', (int) $field->id, 'uses', [
                    'field_name' => $fieldName,
                ]);
                $count++;
            }
        }

        return $count;
    }

    public function syncDashboardLineage(Dashboard $dashboard): int
    {
        $dashboard->loadMissing('widgets.chart');
        $this->forgetSource('dashboard', (int) $dashboard->id);
        $count = 0;

        foreach ($dashboard->widgets as $widget) {
            if (! $widget instanceof DashboardWidget || $widget->chart_id === null) {
                continue;
            }

            $this->relation('dashboard', (int) $dashboard->id, 'chart', (int) $widget->chart_id, 'contains', [
                'widget_id' => $widget->id,
            ]);
            $count++;

            $chart = $widget->chart;

            if ($chart instanceof Chart) {
                $this->relation('dashboard', (int) $dashboard->id, 'dataset', (int) $chart->dataset_id, 'uses');
                $count++;

                foreach ($this->metricCodesFromConfig($chart->config_json ?? []) as $code) {
                    $metric = Metric::query()
                        ->where('dataset_id', $chart->dataset_id)
                        ->where('code', $code)
                        ->first();

                    if ($metric instanceof Metric) {
                        $this->relation('dashboard', (int) $dashboard->id, 'metric', (int) $metric->id, 'uses', [
                            'metric_code' => $code,
                        ]);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    public function syncAccelerationProfileLineage(AccelerationProfile $profile): int
    {
        $this->forgetSource('acceleration_profile', (int) $profile->id);
        $this->relation('acceleration_profile', (int) $profile->id, 'dataset', (int) $profile->dataset_id, 'built_from', [
            'target_table' => $profile->target_table,
            'mode' => $profile->mode,
        ]);

        return 1;
    }

    public function syncAggregateDefinitionLineage(AccelerationAggregateDefinition $definition): int
    {
        $this->forgetSource('aggregate_definition', (int) $definition->id);
        $count = 0;

        $this->relation('aggregate_definition', (int) $definition->id, 'dataset', (int) $definition->dataset_id, 'built_from');
        $count++;
        $this->relation('aggregate_definition', (int) $definition->id, 'acceleration_profile', (int) $definition->detail_profile_id, 'built_from');
        $count++;

        foreach ($this->fieldNamesFromAggregate($definition) as $fieldName) {
            $field = DatasetField::query()
                ->where('dataset_id', $definition->dataset_id)
                ->where('field_name', $fieldName)
                ->first();

            if ($field instanceof DatasetField) {
                $this->relation('aggregate_definition', (int) $definition->id, 'dataset_field', (int) $field->id, 'uses', [
                    'field_name' => $fieldName,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{asset: array<string, mixed>, relations: list<array<string, mixed>>, nodes: list<array<string, mixed>>}
     */
    public function upstream(string $assetType, int $assetId, int $depth = 3): array
    {
        return $this->traverse($assetType, $assetId, min(max($depth, 1), 5), 'upstream');
    }

    /**
     * @return array{asset: array<string, mixed>, relations: list<array<string, mixed>>, nodes: list<array<string, mixed>>}
     */
    public function downstream(string $assetType, int $assetId, int $depth = 3): array
    {
        return $this->traverse($assetType, $assetId, min(max($depth, 1), 5), 'downstream');
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, upstream: array<string, mixed>, downstream: array<string, mixed>}
     */
    public function graph(string $assetType, int $assetId, int $depth = 3): array
    {
        $upstream = $this->upstream($assetType, $assetId, $depth);
        $downstream = $this->downstream($assetType, $assetId, $depth);
        $nodes = collect([$this->node($assetType, $assetId)])
            ->merge($upstream['nodes'])
            ->merge($downstream['nodes'])
            ->unique('id')
            ->values()
            ->all();
        $edges = collect($upstream['relations'])
            ->merge($downstream['relations'])
            ->map(fn (array $relation): array => [
                'source' => $relation['source_key'],
                'target' => $relation['target_key'],
                'relation' => $relation['relation_type'],
                'confidence' => $relation['confidence'],
            ])
            ->unique(fn (array $edge): string => $edge['source'].'|'.$edge['target'].'|'.$edge['relation'])
            ->values()
            ->all();

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'upstream' => $upstream,
            'downstream' => $downstream,
        ];
    }

    private function forgetSource(string $assetType, int $assetId): void
    {
        MetadataLineageRelation::query()
            ->where('source_asset_type', $assetType)
            ->where('source_asset_id', $assetId)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    public function relation(string $sourceType, int $sourceId, string $targetType, int $targetId, string $relationType, array $detail = [], string $confidence = 'high'): MetadataLineageRelation
    {
        $this->assetService->assertAssetType($sourceType);
        $this->assetService->assertAssetType($targetType);

        if (! in_array($relationType, MetadataLineageRelation::RELATION_TYPES, true)) {
            throw ValidationException::withMessages([
                'relation_type' => ['The selected metadata relation type is not supported.'],
            ]);
        }

        if (! in_array($confidence, MetadataLineageRelation::CONFIDENCE_LEVELS, true)) {
            throw ValidationException::withMessages([
                'confidence' => ['The selected metadata lineage confidence is not supported.'],
            ]);
        }

        return MetadataLineageRelation::query()->updateOrCreate(
            [
                'source_asset_type' => $sourceType,
                'source_asset_id' => $sourceId,
                'target_asset_type' => $targetType,
                'target_asset_id' => $targetId,
                'relation_type' => $relationType,
            ],
            [
                'relation_detail_json' => $detail === [] ? null : $detail,
                'confidence' => $confidence,
                'created_by_system' => true,
            ],
        );
    }

    /**
     * @return array{asset: array<string, mixed>, relations: list<array<string, mixed>>, nodes: list<array<string, mixed>>}
     */
    private function traverse(string $assetType, int $assetId, int $depth, string $direction): array
    {
        $this->assetService->assertAssetType($assetType);
        $relations = collect();
        $nodes = collect();
        $this->walk($assetType, $assetId, $depth, $direction, [], $relations, $nodes);

        return [
            'asset' => $this->node($assetType, $assetId),
            'relations' => $relations
                ->unique(fn (array $relation): string => $relation['source_key'].'|'.$relation['target_key'].'|'.$relation['relation_type'])
                ->values()
                ->all(),
            'nodes' => $nodes->unique('id')->values()->all(),
        ];
    }

    private function walk(string $assetType, int $assetId, int $remainingDepth, string $direction, array $visited, Collection $relations, Collection $nodes): void
    {
        if ($remainingDepth <= 0) {
            return;
        }

        $key = $assetType.':'.$assetId;

        if (in_array($key, $visited, true)) {
            return;
        }

        $visited[] = $key;
        $query = MetadataLineageRelation::query();

        if ($direction === 'upstream') {
            $query->where('source_asset_type', $assetType)->where('source_asset_id', $assetId);
        } else {
            $query->where('target_asset_type', $assetType)->where('target_asset_id', $assetId);
        }

        $query->get()->each(function (MetadataLineageRelation $relation) use ($direction, $remainingDepth, $visited, $relations, $nodes): void {
            $relations->push($this->relationPayload($relation));
            $nextType = $direction === 'upstream' ? $relation->target_asset_type : $relation->source_asset_type;
            $nextId = $direction === 'upstream' ? (int) $relation->target_asset_id : (int) $relation->source_asset_id;
            $nodes->push($this->node($nextType, $nextId));
            $this->walk($nextType, $nextId, $remainingDepth - 1, $direction, $visited, $relations, $nodes);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function relationPayload(MetadataLineageRelation $relation): array
    {
        return [
            'source_asset_type' => $relation->source_asset_type,
            'source_asset_id' => $relation->source_asset_id,
            'source_key' => $relation->source_asset_type.':'.$relation->source_asset_id,
            'target_asset_type' => $relation->target_asset_type,
            'target_asset_id' => $relation->target_asset_id,
            'target_key' => $relation->target_asset_type.':'.$relation->target_asset_id,
            'relation_type' => $relation->relation_type,
            'confidence' => $relation->confidence,
            'relation_detail_json' => $relation->relation_detail_json,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function node(string $assetType, int $assetId): array
    {
        $asset = MetadataAsset::query()
            ->where('asset_type', $assetType)
            ->where('asset_id', $assetId)
            ->first();

        return [
            'id' => $assetType.':'.$assetId,
            'type' => $assetType,
            'asset_id' => $assetId,
            'name' => $asset?->name ?? $assetType.' #'.$assetId,
            'code' => $asset?->code,
            'status' => $asset?->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function metricCodesFromConfig(array $config): array
    {
        return collect($config['semantic_metrics'] ?? [])
            ->map(fn (mixed $metric): ?string => is_array($metric) ? ($metric['metric_code'] ?? null) : (is_string($metric) ? $metric : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function dimensionCodesFromConfig(array $config): array
    {
        return collect($config['semantic_dimensions'] ?? [])
            ->map(fn (mixed $dimension): ?string => is_array($dimension) ? ($dimension['dimension_code'] ?? null) : (is_string($dimension) ? $dimension : null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function fieldNamesFromChartConfig(array $config): array
    {
        $fields = collect();

        foreach (['dimensions', 'metrics', 'filters', 'sorts'] as $key) {
            foreach ($config[$key] ?? [] as $item) {
                if (is_string($item)) {
                    $fields->push($item);
                }

                if (is_array($item) && isset($item['field'])) {
                    $fields->push((string) $item['field']);
                }
            }
        }

        return $fields->filter()->unique()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function fieldNamesFromAggregate(AccelerationAggregateDefinition $definition): array
    {
        $fields = collect();

        foreach ($definition->dimensions_json ?? [] as $dimension) {
            if (is_string($dimension)) {
                $fields->push($dimension);
            }

            if (is_array($dimension) && isset($dimension['field'])) {
                $fields->push((string) $dimension['field']);
            }
        }

        foreach ($definition->metrics_json ?? [] as $metric) {
            if (is_array($metric) && isset($metric['field'])) {
                $fields->push((string) $metric['field']);
            }
        }

        if ($definition->time_field !== null) {
            $fields->push($definition->time_field);
        }

        return $fields->filter()->unique()->values()->all();
    }
}
