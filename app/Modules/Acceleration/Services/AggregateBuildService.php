<?php

namespace App\Modules\Acceleration\Services;

use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Models\AccelerationTask;
use App\Modules\Cache\Services\ChartCacheService;
use App\Modules\Chart\Models\Chart;
use RuntimeException;
use Throwable;

class AggregateBuildService
{
    public function __construct(
        private readonly AggregateSchemaService $schemaService,
        private readonly AggregateSqlGenerator $sqlGenerator,
        private readonly ClickHouseClient $client,
        private readonly ChartCacheService $chartCacheService,
    ) {}

    public function process(int $taskId): void
    {
        $task = AccelerationTask::query()
            ->with('profile.aggregateDefinition.dataset', 'profile.aggregateDefinition.columns', 'profile.aggregateDefinition.detailProfile.columns')
            ->findOrFail($taskId);
        $profile = $task->profile;
        $definition = $profile->aggregateDefinition;
        $startedAt = microtime(true);

        if (! $definition instanceof AccelerationAggregateDefinition) {
            throw new RuntimeException('Aggregate definition is missing for this task.');
        }

        $task->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        $definition->forceFill([
            'status' => 'building',
            'last_refresh_at' => now(),
            'last_error_message' => null,
        ])->save();

        $profile->forceFill([
            'status' => 'building',
            'last_refresh_at' => now(),
            'last_error_message' => null,
        ])->save();

        try {
            $definition->loadMissing(['dataset', 'columns', 'detailProfile.columns']);

            if (! $definition->detailProfile instanceof AccelerationProfile || $definition->detailProfile->status !== 'active') {
                throw new RuntimeException('The source detail acceleration profile must be active before building an aggregate table.');
            }

            if ($definition->columns->isEmpty()) {
                $this->schemaService->syncColumns($definition);
                $definition->load('columns');
            }

            $database = $this->sqlGenerator->database($definition);
            $this->client->execute($this->sqlGenerator->createDatabaseSql($definition), $database);
            $this->client->execute($this->sqlGenerator->dropTableSql($definition), $database);
            $this->client->execute($this->sqlGenerator->createTableSql($definition), $database);
            $this->client->execute($this->sqlGenerator->insertFromDetailSql($definition), $database);

            $rowCount = $this->rowCount($definition);

            $definition->forceFill([
                'status' => 'active',
                'row_count' => $rowCount,
                'version' => $definition->version + 1,
                'last_success_at' => now(),
                'last_error_message' => null,
            ])->save();

            $profile->forceFill([
                'status' => 'active',
                'row_count' => $rowCount,
                'version' => $profile->version + 1,
                'last_success_at' => now(),
                'last_error_message' => null,
                'config_json' => [
                    ...($profile->config_json ?? []),
                    'aggregate_definition_id' => $definition->id,
                    'source_detail_profile_id' => $definition->detail_profile_id,
                    'time_field' => $definition->time_field,
                    'time_grain' => $definition->time_grain,
                    'dimensions' => $definition->dimensions_json ?? [],
                    'metrics' => $definition->metrics_json ?? [],
                ],
            ])->save();

            $task->forceFill([
                'status' => 'success',
                'finished_at' => now(),
                'target_row_count' => $rowCount,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ])->save();

            $this->forgetDatasetChartCaches((int) $definition->dataset_id);
        } catch (Throwable $exception) {
            $definition->forceFill([
                'status' => 'failed',
                'last_error_message' => $exception->getMessage(),
            ])->save();

            $profile->forceFill([
                'status' => 'failed',
                'last_error_message' => $exception->getMessage(),
            ])->save();

            $task->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function rowCount(AccelerationAggregateDefinition $definition): int
    {
        $rows = $this->client->select($this->sqlGenerator->countSql($definition), $this->sqlGenerator->database($definition));
        $first = $rows[0] ?? [];
        $value = $first['row_count'] ?? $first['count()'] ?? reset($first);

        return (int) $value;
    }

    private function forgetDatasetChartCaches(int $datasetId): void
    {
        Chart::query()
            ->where('dataset_id', $datasetId)
            ->pluck('id')
            ->each(fn (int $chartId): mixed => $this->chartCacheService->forget($chartId));
    }
}
