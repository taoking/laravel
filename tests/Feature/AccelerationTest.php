<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Acceleration\Drivers\AccelerationDriverInterface;
use App\Modules\Acceleration\Jobs\BuildAccelerationTableJob;
use App\Modules\Acceleration\Jobs\BuildAggregateTableJob;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Services\AccelerationDriverManager;
use App\Modules\Acceleration\Services\AccelerationSyncService;
use App\Modules\Acceleration\Services\AggregateBuildService;
use App\Modules\Acceleration\Services\ClickHouseClient;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Query\DTO\CompiledQuery;
use App\Modules\Query\DTO\QueryExecutionResult;
use App\Modules\Query\Models\QueryLog;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AccelerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_acceleration_profile_with_default_column_mapping(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();

        $this->postJson('/api/acceleration/profiles', [
            'dataset_id' => $dataset->id,
            'name' => 'Orders ClickHouse',
            'engine_type' => 'clickhouse',
            'mode' => 'detail_table',
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.dataset_id', $dataset->id)
            ->assertJsonPath('data.engine_type', 'clickhouse')
            ->assertJsonCount(4, 'data.columns');

        $this->assertDatabaseHas('acceleration_profiles', [
            'dataset_id' => $dataset->id,
            'name' => 'Orders ClickHouse',
            'status' => 'disabled',
        ]);
        $this->assertDatabaseHas('acceleration_columns', [
            'source_field_name' => 'amount',
            'target_field_name' => 'amount',
        ]);
    }

    public function test_dataset_build_endpoint_creates_task_and_dispatches_queue_job(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();

        $this->postJson("/api/datasets/{$dataset->id}/acceleration/build", [
            'engine_type' => 'clickhouse',
            'mode' => 'detail_table',
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.profile.status', 'building')
            ->assertJsonPath('data.task.status', 'pending');

        Queue::assertPushed(BuildAccelerationTableJob::class);
        $this->assertDatabaseHas('acceleration_tasks', [
            'task_type' => 'full_sync',
            'status' => 'pending',
        ]);
    }

    public function test_acceleration_sync_service_updates_task_and_profile_status(): void
    {
        $dataset = $this->createDataset();
        $profile = $this->createActiveProfile($dataset);
        $task = $profile->tasks()->create([
            'task_type' => 'full_sync',
            'status' => 'pending',
        ]);

        $driver = Mockery::mock(AccelerationDriverInterface::class);
        $driver->shouldReceive('dropTable')->once()->with(Mockery::type(AccelerationProfile::class));
        $driver->shouldReceive('createDetailTable')->once()->with(Mockery::type(AccelerationProfile::class));
        $driver->shouldReceive('insertRows')->once()->andReturn(2);
        $manager = Mockery::mock(AccelerationDriverManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(AccelerationDriverManager::class, $manager);

        $builderWithRows = Mockery::mock();
        $builderWithRows->shouldReceive('select')->once()->andReturnSelf();
        $builderWithRows->shouldReceive('limit')->once()->andReturnSelf();
        $builderWithRows->shouldReceive('offset')->once()->with(0)->andReturnSelf();
        $builderWithRows->shouldReceive('get')->once()->andReturn(collect([
            (object) ['province' => 'GD', 'amount' => '1200.00', 'year' => 2026, 'ordered_at' => '2026-06-01 00:00:00'],
            (object) ['province' => 'BJ', 'amount' => '800.00', 'year' => 2026, 'ordered_at' => '2026-06-02 00:00:00'],
        ]));

        $builderEmpty = Mockery::mock();
        $builderEmpty->shouldReceive('select')->once()->andReturnSelf();
        $builderEmpty->shouldReceive('limit')->once()->andReturnSelf();
        $builderEmpty->shouldReceive('offset')->once()->with(5000)->andReturnSelf();
        $builderEmpty->shouldReceive('get')->once()->andReturn(collect());

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('table')->twice()->with('orders')->andReturn($builderWithRows, $builderEmpty);
        $factory = Mockery::mock(DataSourceConnectionFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($connection);
        $factory->shouldReceive('disconnect')->once();
        $this->app->instance(DataSourceConnectionFactory::class, $factory);

        app(AccelerationSyncService::class)->process($task->id);

        $this->assertDatabaseHas('acceleration_tasks', [
            'id' => $task->id,
            'status' => 'success',
            'source_row_count' => 2,
            'target_row_count' => 2,
        ]);
        $this->assertDatabaseHas('acceleration_profiles', [
            'id' => $profile->id,
            'status' => 'active',
            'row_count' => 2,
            'version' => 4,
        ]);
    }

    public function test_query_uses_acceleration_when_profile_matches(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $profile = $this->createActiveProfile($dataset);
        $compiled = new CompiledQuery(
            sql: 'select `province` as `province`, sum(`amount`) as `amount_sum` from `bi_accelerator`.`orders_acc` group by `province` limit 100 offset 0',
            bindings: [],
            columns: [
                ['name' => 'province', 'label' => 'Province', 'type' => 'string'],
                ['name' => 'amount_sum', 'label' => 'Amount', 'type' => 'number'],
            ],
            hash: 'accelerated_hash',
        );
        $driver = Mockery::mock(AccelerationDriverInterface::class);
        $driver->shouldReceive('supports')->andReturn(true);
        $driver->shouldReceive('tableExists')->andReturn(true);
        $driver->shouldReceive('generateQuerySql')->andReturn($compiled);
        $driver->shouldReceive('executeQuery')->andReturn(new QueryExecutionResult(
            rows: [['province' => 'GD', 'amount_sum' => '1200.00']],
            elapsedMs: 8,
        ));
        $manager = Mockery::mock(AccelerationDriverManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(AccelerationDriverManager::class, $manager);

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.meta.acceleration_hit', true)
            ->assertJsonPath('data.meta.acceleration_profile_id', $profile->id)
            ->assertJsonPath('data.rows.0.province', 'GD');

        $this->assertDatabaseHas('query_logs', [
            'dataset_id' => $dataset->id,
            'acceleration_hit' => true,
            'acceleration_profile_id' => $profile->id,
            'acceleration_engine' => 'clickhouse',
            'fallback_used' => false,
        ]);
    }

    public function test_can_create_aggregate_definition_and_dispatch_build_job(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $detailProfile = $this->createActiveProfile($dataset);

        $response = $this->postJson("/api/datasets/{$dataset->id}/acceleration/aggregates", [
            'detail_profile_id' => $detailProfile->id,
            'name' => 'Monthly Province Sales',
            'time_field' => 'ordered_at',
            'time_grain' => 'month',
            'dimensions' => ['province'],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.dataset_id', $dataset->id)
            ->assertJsonPath('data.time_grain', 'month')
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonCount(3, 'data.columns');

        $definitionId = $response->json('data.id');
        $this->assertDatabaseHas('acceleration_aggregate_definitions', [
            'id' => $definitionId,
            'dataset_id' => $dataset->id,
            'detail_profile_id' => $detailProfile->id,
            'name' => 'Monthly Province Sales',
        ]);
        $this->assertDatabaseHas('acceleration_profiles', [
            'dataset_id' => $dataset->id,
            'mode' => 'aggregate_table',
        ]);
        $this->assertDatabaseHas('acceleration_aggregate_columns', [
            'aggregate_definition_id' => $definitionId,
            'target_field_name' => 'amount_sum',
            'column_role' => 'metric',
            'aggregate_function' => 'sum',
        ]);

        $this->postJson("/api/acceleration/aggregates/{$definitionId}/build")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.task_type', 'build_aggregate');

        Queue::assertPushed(BuildAggregateTableJob::class);
        $this->assertDatabaseHas('acceleration_tasks', [
            'task_type' => 'build_aggregate',
            'status' => 'pending',
        ]);
    }

    public function test_aggregate_build_service_creates_table_from_detail_profile(): void
    {
        $dataset = $this->createDataset();
        $detailProfile = $this->createActiveProfile($dataset);
        $definition = $this->createActiveAggregateDefinition($dataset, $detailProfile, status: 'building');
        $task = $definition->aggregateProfile->tasks()->create([
            'task_type' => 'build_aggregate',
            'status' => 'pending',
        ]);

        $client = Mockery::mock(ClickHouseClient::class);
        $client->shouldReceive('execute')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'CREATE DATABASE')), 'bi_accelerator')->andReturn(['ok' => true, 'body' => '']);
        $client->shouldReceive('execute')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'DROP TABLE')), 'bi_accelerator')->andReturn(['ok' => true, 'body' => '']);
        $client->shouldReceive('execute')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'CREATE TABLE') && str_contains($sql, '`amount_sum` Float64')), 'bi_accelerator')->andReturn(['ok' => true, 'body' => '']);
        $client->shouldReceive('execute')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'INSERT INTO') && str_contains($sql, 'sum(`amount`) as `amount_sum`')), 'bi_accelerator')->andReturn(['ok' => true, 'body' => '']);
        $client->shouldReceive('select')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'count() as row_count')), 'bi_accelerator')->andReturn([
            ['row_count' => 2],
        ]);
        $this->app->instance(ClickHouseClient::class, $client);

        app(AggregateBuildService::class)->process($task->id);

        $this->assertDatabaseHas('acceleration_aggregate_definitions', [
            'id' => $definition->id,
            'status' => 'active',
            'row_count' => 2,
            'version' => 3,
        ]);
        $this->assertDatabaseHas('acceleration_profiles', [
            'id' => $definition->aggregate_profile_id,
            'status' => 'active',
            'row_count' => 2,
        ]);
        $this->assertDatabaseHas('acceleration_tasks', [
            'id' => $task->id,
            'status' => 'success',
            'target_row_count' => 2,
        ]);
    }

    public function test_query_prefers_aggregate_table_when_definition_matches(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $detailProfile = $this->createActiveProfile($dataset);
        $definition = $this->createActiveAggregateDefinition($dataset, $detailProfile);

        $client = Mockery::mock(ClickHouseClient::class);
        $client->shouldReceive('select')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'EXISTS TABLE')), 'bi_accelerator')->andReturn([
            ['result' => 1],
        ]);
        $client->shouldReceive('select')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'from `bi_accelerator`.`orders_agg`') && str_contains($sql, 'sum(`amount_sum`)')), 'bi_accelerator')->andReturn([
            ['province' => 'GD', 'amount_sum' => '1200.00'],
        ]);
        $this->app->instance(ClickHouseClient::class, $client);

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.meta.acceleration_hit', true)
            ->assertJsonPath('data.meta.acceleration_mode', 'aggregate_table')
            ->assertJsonPath('data.meta.aggregate_definition_id', $definition->id)
            ->assertJsonPath('data.rows.0.province', 'GD');

        $this->assertDatabaseHas('query_logs', [
            'dataset_id' => $dataset->id,
            'acceleration_hit' => true,
            'acceleration_profile_id' => $definition->aggregate_profile_id,
            'acceleration_mode' => 'aggregate_table',
            'aggregate_definition_id' => $definition->id,
            'aggregate_table' => 'orders_agg',
            'fallback_used' => false,
        ]);
    }

    public function test_query_falls_back_to_detail_when_aggregate_execution_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $detailProfile = $this->createActiveProfile($dataset);
        $definition = $this->createActiveAggregateDefinition($dataset, $detailProfile);
        $compiled = new CompiledQuery(
            sql: 'select `province` as `province`, sum(`amount`) as `amount_sum` from `bi_accelerator`.`orders_acc` group by `province` limit 100 offset 0',
            bindings: [],
            columns: [
                ['name' => 'province', 'label' => 'Province', 'type' => 'string'],
                ['name' => 'amount_sum', 'label' => 'Amount', 'type' => 'number'],
            ],
            hash: 'detail_hash',
        );

        $client = Mockery::mock(ClickHouseClient::class);
        $client->shouldReceive('select')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'EXISTS TABLE')), 'bi_accelerator')->andReturn([
            ['result' => 1],
        ]);
        $client->shouldReceive('select')->once()->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'from `bi_accelerator`.`orders_agg`')), 'bi_accelerator')->andThrow(new RuntimeException('Aggregate unavailable'));
        $this->app->instance(ClickHouseClient::class, $client);

        $driver = Mockery::mock(AccelerationDriverInterface::class);
        $driver->shouldReceive('supports')->andReturn(true);
        $driver->shouldReceive('tableExists')->andReturn(true);
        $driver->shouldReceive('generateQuerySql')->andReturn($compiled);
        $driver->shouldReceive('executeQuery')->andReturn(new QueryExecutionResult(
            rows: [['province' => 'GD', 'amount_sum' => '1200.00']],
            elapsedMs: 8,
        ));
        $manager = Mockery::mock(AccelerationDriverManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(AccelerationDriverManager::class, $manager);

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.meta.acceleration_hit', true)
            ->assertJsonPath('data.meta.acceleration_mode', 'detail_table')
            ->assertJsonPath('data.meta.aggregate_definition_id', $definition->id)
            ->assertJsonPath('data.meta.detail_fallback_used', true)
            ->assertJsonPath('data.meta.fallback_used', true);

        $log = QueryLog::query()->latest('id')->firstOrFail();
        $this->assertTrue($log->acceleration_hit);
        $this->assertTrue($log->fallback_used);
        $this->assertTrue($log->detail_fallback_used);
        $this->assertSame('detail_table', $log->acceleration_mode);
        $this->assertSame($definition->id, $log->aggregate_definition_id);
        $this->assertSame('Aggregate unavailable', $log->fallback_reason);
    }

    public function test_query_skips_aggregate_when_filter_field_is_not_aggregated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $detailProfile = $this->createActiveProfile($dataset);
        $this->createActiveAggregateDefinition($dataset, $detailProfile);
        $compiled = new CompiledQuery(
            sql: 'select `province` as `province`, sum(`amount`) as `amount_sum` from `bi_accelerator`.`orders_acc` where `year` = 2026 group by `province` limit 100 offset 0',
            bindings: [],
            columns: [
                ['name' => 'province', 'label' => 'Province', 'type' => 'string'],
                ['name' => 'amount_sum', 'label' => 'Amount', 'type' => 'number'],
            ],
            hash: 'detail_hash',
        );

        $driver = Mockery::mock(AccelerationDriverInterface::class);
        $driver->shouldReceive('supports')->andReturn(true);
        $driver->shouldReceive('tableExists')->andReturn(true);
        $driver->shouldReceive('generateQuerySql')->andReturn($compiled);
        $driver->shouldReceive('executeQuery')->andReturn(new QueryExecutionResult(
            rows: [['province' => 'GD', 'amount_sum' => '1200.00']],
            elapsedMs: 8,
        ));
        $manager = Mockery::mock(AccelerationDriverManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(AccelerationDriverManager::class, $manager);

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
            'filters' => [
                ['field' => 'year', 'operator' => '=', 'value' => 2026],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.meta.acceleration_hit', true)
            ->assertJsonPath('data.meta.acceleration_mode', 'detail_table')
            ->assertJsonPath('data.meta.detail_fallback_used', false)
            ->assertJsonPath('data.meta.fallback_used', false);
    }

    public function test_query_falls_back_to_source_when_acceleration_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $profile = $this->createActiveProfile($dataset);
        $connection = $this->fakeConnection();
        $sourceSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` limit 100 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($sourceSql, [])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1000.00'],
            ]);

        $driver = Mockery::mock(AccelerationDriverInterface::class);
        $driver->shouldReceive('supports')->andReturn(true);
        $driver->shouldReceive('tableExists')->andReturn(true);
        $driver->shouldReceive('generateQuerySql')->andReturn(new CompiledQuery(
            sql: 'select broken from `bi_accelerator`.`orders_acc`',
            bindings: [],
            columns: [],
            hash: 'accelerated_hash',
        ));
        $driver->shouldReceive('executeQuery')->andThrow(new RuntimeException('ClickHouse unavailable'));
        $manager = Mockery::mock(AccelerationDriverManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(AccelerationDriverManager::class, $manager);

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.meta.acceleration_hit', false)
            ->assertJsonPath('data.meta.fallback_used', true)
            ->assertJsonPath('data.rows.0.amount_sum', '1000.00');

        $log = QueryLog::query()->latest('id')->firstOrFail();
        $this->assertFalse($log->acceleration_hit);
        $this->assertTrue($log->fallback_used);
        $this->assertSame($profile->id, $log->acceleration_profile_id);
        $this->assertSame('ClickHouse unavailable', $log->fallback_reason);
    }

    public function test_clickhouse_client_supports_ping_execute_and_insert(): void
    {
        config([
            'bi_acceleration.clickhouse.host' => 'clickhouse',
            'bi_acceleration.clickhouse.port' => 8123,
            'bi_acceleration.clickhouse.database' => 'bi_accelerator',
            'bi_acceleration.clickhouse.username' => 'default',
            'bi_acceleration.clickhouse.password' => '',
        ]);
        Http::fake([
            'http://clickhouse:8123/*' => Http::response(['data' => [['ok' => 1]]], 200),
        ]);

        $client = app(ClickHouseClient::class);

        $this->assertTrue($client->ping());
        $this->assertTrue($client->execute('CREATE DATABASE IF NOT EXISTS `bi_accelerator`')['ok']);
        $this->assertSame(1, $client->insert('orders_acc', [
            ['province' => 'GD', 'amount' => 1200],
        ]));
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
            ['field_name' => 'year', 'display_name' => 'Year', 'normalized_type' => 'integer', 'semantic_type' => 'time', 'is_dimension' => true, 'is_metric' => false, 'default_aggregate' => 'none', 'sort_order' => 3],
            ['field_name' => 'ordered_at', 'display_name' => 'Ordered At', 'normalized_type' => 'datetime', 'semantic_type' => 'time', 'is_dimension' => true, 'is_metric' => false, 'default_aggregate' => 'none', 'sort_order' => 4],
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

    private function createActiveProfile(Dataset $dataset): AccelerationProfile
    {
        $profile = AccelerationProfile::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Orders Acceleration',
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
                    'integer' => 'Nullable(Int64)',
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

    /**
     * @param  list<string>  $dimensions
     * @param  list<array{field: string, aggregate: string, alias?: string}>  $metrics
     */
    private function createActiveAggregateDefinition(
        Dataset $dataset,
        AccelerationProfile $detailProfile,
        array $dimensions = ['province'],
        array $metrics = [['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum']],
        ?string $timeField = null,
        string $timeGrain = 'none',
        string $status = 'active',
    ): AccelerationAggregateDefinition {
        $aggregateProfile = AccelerationProfile::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Orders Aggregate Profile',
            'engine_type' => 'clickhouse',
            'mode' => 'aggregate_table',
            'status' => $status,
            'source_connection_id' => $dataset->data_source_id,
            'target_database' => 'bi_accelerator',
            'target_table' => 'orders_agg',
            'refresh_type' => 'manual',
            'version' => 5,
            'config_json' => [],
        ]);

        $definition = AccelerationAggregateDefinition::query()->create([
            'dataset_id' => $dataset->id,
            'detail_profile_id' => $detailProfile->id,
            'aggregate_profile_id' => $aggregateProfile->id,
            'name' => 'Orders Aggregate',
            'status' => $status,
            'target_database' => 'bi_accelerator',
            'target_table' => 'orders_agg',
            'time_field' => $timeField,
            'time_grain' => $timeGrain,
            'dimensions_json' => $dimensions,
            'metrics_json' => $metrics,
            'filters_json' => [],
            'refresh_type' => 'manual',
            'version' => 2,
        ]);

        if ($timeField !== null && $timeGrain !== 'none') {
            $definition->columns()->create([
                'source_field_name' => $timeField,
                'target_field_name' => "{$timeField}_{$timeGrain}",
                'column_role' => 'time_grain',
                'aggregate_function' => 'none',
                'source_type' => 'datetime',
                'target_type' => $timeGrain === 'year' ? 'UInt16' : 'Date',
            ]);
        }

        foreach ($dimensions as $dimension) {
            $definition->columns()->create([
                'source_field_name' => $dimension,
                'target_field_name' => $dimension,
                'column_role' => 'dimension',
                'aggregate_function' => 'none',
                'source_type' => 'string',
                'target_type' => 'Nullable(String)',
            ]);
        }

        foreach ($metrics as $metric) {
            $definition->columns()->create([
                'source_field_name' => $metric['field'],
                'target_field_name' => $metric['alias'] ?? "{$metric['field']}_{$metric['aggregate']}",
                'column_role' => 'metric',
                'aggregate_function' => $metric['aggregate'],
                'source_type' => 'decimal',
                'target_type' => in_array($metric['aggregate'], ['count', 'countDistinct'], true) ? 'UInt64' : 'Float64',
            ]);
        }

        return $definition->refresh()->load(['aggregateProfile', 'detailProfile', 'columns']);
    }

    private function fakeConnection(): ConnectionInterface
    {
        $connection = Mockery::mock(ConnectionInterface::class);

        $factory = Mockery::mock(DataSourceConnectionFactory::class);
        $factory->shouldReceive('make')->andReturn($connection);
        $factory->shouldReceive('disconnect')->zeroOrMoreTimes();
        $this->app->instance(DataSourceConnectionFactory::class, $factory);

        return $connection;
    }
}
