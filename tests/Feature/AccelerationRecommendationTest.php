<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Acceleration\Jobs\BuildAccelerationTableJob;
use App\Modules\Acceleration\Jobs\BuildAggregateTableJob;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Models\AccelerationRecommendation;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Permission\Models\Role;
use App\Modules\Query\Models\QueryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccelerationRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_deduplicate_and_accept_aggregate_recommendation(): void
    {
        Queue::fake();
        config([
            'bi_acceleration.recommendation.min_query_count' => 3,
            'bi_acceleration.recommendation.slow_query_threshold_ms' => 3000,
        ]);
        Sanctum::actingAs($this->adminUser());
        $dataset = $this->createDataset();
        $this->createActiveProfile($dataset);
        $chart = $this->createChart($dataset);
        $this->createQueryLogs($dataset, $chart, 3, 3600);

        $this->artisan('bi:acceleration:recommend', ['--days' => 7, '--dry-run' => true])
            ->assertExitCode(0);
        $this->assertDatabaseCount('acceleration_recommendations', 0);

        $this->postJson('/api/acceleration/recommendations/generate', [
            'days' => 7,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.generated_count', 1)
            ->assertJsonPath('data.query_count', 3);

        $this->postJson('/api/acceleration/recommendations/generate', [
            'days' => 7,
        ])
            ->assertOk()
            ->assertJsonPath('data.generated_count', 0);

        $recommendation = AccelerationRecommendation::query()->firstOrFail();
        $this->assertSame('aggregate_table', $recommendation->recommendation_type);
        $this->assertSame(['province'], $recommendation->dimensions_json);
        $this->assertSame('ordered_at', $recommendation->time_field);
        $this->assertSame('month', $recommendation->time_grain);

        $this->getJson('/api/acceleration/recommendations')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->postJson("/api/acceleration/recommendations/{$recommendation->id}/accept")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.recommendation.status', 'created')
            ->assertJsonPath('data.task.task_type', 'build_aggregate');

        Queue::assertPushed(BuildAggregateTableJob::class);
        $this->assertDatabaseHas('acceleration_aggregate_definitions', [
            'dataset_id' => $dataset->id,
            'status' => 'building',
            'time_field' => 'ordered_at',
            'time_grain' => 'month',
        ]);
        $this->assertDatabaseHas('acceleration_tasks', [
            'task_type' => 'build_aggregate',
            'status' => 'pending',
        ]);
    }

    public function test_can_reject_recommendation(): void
    {
        Sanctum::actingAs($this->adminUser());
        $dataset = $this->createDataset();
        $recommendation = AccelerationRecommendation::query()->create([
            'dataset_id' => $dataset->id,
            'recommendation_type' => 'aggregate_table',
            'status' => 'pending',
            'priority' => 'medium',
            'reason' => 'test',
            'dimensions_json' => ['province'],
            'metrics_json' => [['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum']],
            'filters_json' => [],
            'time_grain' => 'none',
            'estimated_query_count' => 3,
            'estimated_benefit_score' => 1000,
        ]);

        $this->postJson("/api/acceleration/recommendations/{$recommendation->id}/reject", [
            'reason' => 'not useful',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('acceleration_recommendations', [
            'id' => $recommendation->id,
            'status' => 'rejected',
        ]);
    }

    public function test_refresh_due_command_dispatches_due_detail_profile_schedule(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->adminUser());
        $dataset = $this->createDataset();
        $profile = $this->createActiveProfile($dataset);

        $response = $this->postJson('/api/acceleration/refresh-schedules', [
            'target_type' => 'detail_profile',
            'target_id' => $profile->id,
            'refresh_type' => 'hourly',
            'next_run_at' => now()->subMinute()->toISOString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.target_type', 'detail_profile');

        $scheduleId = $response->json('data.id');

        $this->artisan('bi:acceleration:refresh-due', ['--dry-run' => true])
            ->assertExitCode(0);
        $this->assertDatabaseHas('acceleration_refresh_schedules', [
            'id' => $scheduleId,
            'last_status' => null,
        ]);

        $this->artisan('bi:acceleration:refresh-due')
            ->assertExitCode(0);

        Queue::assertPushed(BuildAccelerationTableJob::class);
        $this->assertDatabaseHas('acceleration_refresh_schedules', [
            'id' => $scheduleId,
            'last_status' => 'dispatched',
        ]);
        $this->assertDatabaseHas('acceleration_tasks', [
            'acceleration_profile_id' => $profile->id,
            'task_type' => 'full_sync',
            'status' => 'pending',
        ]);
    }

    public function test_refresh_schedule_run_now_dispatches_aggregate_refresh(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->adminUser());
        $dataset = $this->createDataset();
        $detailProfile = $this->createActiveProfile($dataset);
        $definition = $this->createAggregateDefinition($dataset, $detailProfile);

        $response = $this->postJson('/api/acceleration/refresh-schedules', [
            'target_type' => 'aggregate_definition',
            'target_id' => $definition->id,
            'refresh_type' => 'manual',
        ])
            ->assertCreated();

        $scheduleId = $response->json('data.id');

        $this->postJson("/api/acceleration/refresh-schedules/{$scheduleId}/run-now")
            ->assertOk()
            ->assertJsonPath('data.task.task_type', 'refresh_aggregate');

        Queue::assertPushed(BuildAggregateTableJob::class);
        $this->assertDatabaseHas('acceleration_refresh_schedules', [
            'id' => $scheduleId,
            'last_status' => 'dispatched',
        ]);
    }

    public function test_benefit_report_api_and_command_return_acceleration_statistics(): void
    {
        Sanctum::actingAs($this->adminUser());
        $dataset = $this->createDataset();
        $chart = $this->createChart($dataset);

        QueryLog::query()->create($this->queryLogPayload($dataset, $chart, 4000, ['acceleration_hit' => false]));
        QueryLog::query()->create($this->queryLogPayload($dataset, $chart, 800, ['acceleration_hit' => true, 'acceleration_mode' => 'detail_table']));
        QueryLog::query()->create($this->queryLogPayload($dataset, $chart, 120, ['acceleration_hit' => true, 'acceleration_mode' => 'aggregate_table']));
        QueryLog::query()->create($this->queryLogPayload($dataset, $chart, 0, ['cached' => true, 'acceleration_hit' => true, 'acceleration_mode' => 'aggregate_table']));
        QueryLog::query()->create($this->queryLogPayload($dataset, $chart, 3900, ['fallback_used' => true, 'acceleration_hit' => false]));

        $this->getJson('/api/acceleration/benefit-report?days=7')
            ->assertOk()
            ->assertJsonPath('data.query_count', 5)
            ->assertJsonPath('data.raw_query_count', 2)
            ->assertJsonPath('data.detail_hit_count', 1)
            ->assertJsonPath('data.aggregate_hit_count', 1)
            ->assertJsonPath('data.cache_hit_count', 1)
            ->assertJsonPath('data.fallback_count', 1);

        $this->getJson("/api/acceleration/benefit-report/datasets/{$dataset->id}?days=7")
            ->assertOk()
            ->assertJsonPath('data.query_count', 5);

        $this->getJson("/api/acceleration/benefit-report/charts/{$chart->id}?days=7")
            ->assertOk()
            ->assertJsonPath('data.query_count', 5);

        $this->artisan('bi:acceleration:benefit-report', ['--days' => 7])
            ->assertExitCode(0);
        $this->assertDatabaseHas('acceleration_benefit_reports', [
            'query_count' => 5,
            'raw_query_count' => 2,
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Administrator',
            'code' => 'admin',
            'guard_name' => 'sanctum',
            'is_system' => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function createDataset(): Dataset
    {
        $dataSource = DataSource::query()->create([
            'name' => 'Analytics MySQL',
            'type' => 'mysql',
            'host' => 'mysql',
            'port' => 3306,
            'database_name' => 'analytics',
            'username' => 'reporter',
            'password_encrypted' => app(DataSourcePasswordEncryptor::class)->encrypt('secret'),
            'charset' => 'utf8mb4',
            'timezone' => '+00:00',
            'status' => 'active',
        ]);

        $dataset = Dataset::query()->create([
            'name' => 'Orders Dataset',
            'data_source_id' => $dataSource->id,
            'dataset_type' => 'single_table',
            'main_table' => 'orders',
            'status' => 'active',
        ]);

        foreach ([
            ['field_name' => 'province', 'display_name' => 'Province', 'normalized_type' => 'string', 'semantic_type' => 'province', 'is_dimension' => true, 'is_metric' => false, 'default_aggregate' => 'none', 'sort_order' => 1],
            ['field_name' => 'amount', 'display_name' => 'Amount', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'default_aggregate' => 'sum', 'sort_order' => 2],
            ['field_name' => 'ordered_at', 'display_name' => 'Ordered At', 'normalized_type' => 'datetime', 'semantic_type' => 'time', 'is_dimension' => true, 'is_metric' => false, 'default_aggregate' => 'none', 'sort_order' => 3],
        ] as $field) {
            DatasetField::query()->create([
                'dataset_id' => $dataset->id,
                'table_name' => 'orders',
                'field_alias' => $field['field_name'],
                'source_type' => 'physical',
                'is_visible' => true,
                'is_filterable' => true,
                'expression' => null,
                ...$field,
            ]);
        }

        return $dataset->refresh();
    }

    private function createChart(Dataset $dataset): Chart
    {
        return Chart::query()->create([
            'name' => 'Province Sales',
            'dataset_id' => $dataset->id,
            'chart_type' => 'bar',
            'config_json' => [
                'dimensions' => [
                    ['field' => 'ordered_at', 'time_granularity' => 'month', 'alias' => 'ordered_month'],
                    ['field' => 'province'],
                ],
                'metrics' => [
                    ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
                ],
                'filters' => [],
            ],
            'style_json' => [],
            'status' => 'active',
        ]);
    }

    private function createActiveProfile(Dataset $dataset): AccelerationProfile
    {
        $profile = AccelerationProfile::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Orders Detail',
            'engine_type' => 'clickhouse',
            'mode' => 'detail_table',
            'status' => 'active',
            'source_connection_id' => $dataset->data_source_id,
            'target_database' => 'bi_accelerator',
            'target_table' => 'orders_acc',
            'refresh_type' => 'manual',
            'version' => 3,
        ]);

        foreach ($dataset->fields as $field) {
            $profile->columns()->create([
                'dataset_field_id' => $field->id,
                'source_field_name' => $field->field_name,
                'target_field_name' => $field->field_name,
                'source_type' => $field->normalized_type,
                'target_type' => match ($field->normalized_type) {
                    'decimal' => 'Nullable(Decimal(18, 4))',
                    'datetime' => 'Nullable(DateTime)',
                    default => 'Nullable(String)',
                },
                'is_dimension' => (bool) $field->is_dimension,
                'is_metric' => (bool) $field->is_metric,
                'aggregate_functions_json' => [],
                'is_partition_key' => $field->field_name === 'ordered_at',
                'is_order_key' => in_array($field->field_name, ['ordered_at', 'province'], true),
                'is_nullable' => true,
            ]);
        }

        return $profile->refresh();
    }

    private function createAggregateDefinition(Dataset $dataset, AccelerationProfile $detailProfile)
    {
        $aggregateProfile = AccelerationProfile::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Orders Aggregate Profile',
            'engine_type' => 'clickhouse',
            'mode' => 'aggregate_table',
            'status' => 'active',
            'source_connection_id' => $dataset->data_source_id,
            'target_database' => 'bi_accelerator',
            'target_table' => 'orders_agg',
            'refresh_type' => 'manual',
            'version' => 2,
        ]);

        $definition = $dataset->accelerationAggregateDefinitions()->create([
            'detail_profile_id' => $detailProfile->id,
            'aggregate_profile_id' => $aggregateProfile->id,
            'name' => 'Orders Aggregate',
            'status' => 'active',
            'target_database' => 'bi_accelerator',
            'target_table' => 'orders_agg',
            'time_field' => 'ordered_at',
            'time_grain' => 'month',
            'dimensions_json' => ['province'],
            'metrics_json' => [['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum']],
            'filters_json' => [],
            'refresh_type' => 'manual',
            'version' => 2,
        ]);

        return $definition->refresh();
    }

    private function createQueryLogs(Dataset $dataset, Chart $chart, int $count, int $elapsedMs): void
    {
        for ($index = 0; $index < $count; $index++) {
            QueryLog::query()->create($this->queryLogPayload($dataset, $chart, $elapsedMs + $index, [
                'acceleration_hit' => false,
                'acceleration_mode' => null,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function queryLogPayload(Dataset $dataset, Chart $chart, int $elapsedMs, array $overrides = []): array
    {
        return [
            'dataset_id' => $dataset->id,
            'chart_id' => $chart->id,
            'query_hash' => sha1((string) microtime(true).random_int(1, 100000)),
            'sql' => 'select province, sum(amount) from orders group by province',
            'bindings_json' => [],
            'elapsed_ms' => $elapsedMs,
            'row_count' => 1,
            'cached' => false,
            'is_slow' => $elapsedMs >= 3000,
            'status' => 'success',
            'fallback_used' => false,
            ...$overrides,
        ];
    }
}
