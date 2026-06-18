<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Query\Models\QueryLog;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class QueryEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_engine_executes_group_by_query_with_bindings_and_logs_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset();
        $connection = $this->fakeConnection();

        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` where `year` = ? and `province` in (?, ?) group by `province` order by `amount_sum` desc limit 100 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [2026, 'GD', 'BJ'])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1200.00'],
            ]);

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
                ['field' => 'province', 'operator' => 'in', 'value' => ['GD', 'BJ']],
            ],
            'sorts' => [
                ['field' => 'amount_sum', 'direction' => 'desc'],
            ],
            'limit' => 100,
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.columns.0.name', 'province')
            ->assertJsonPath('data.columns.1.name', 'amount_sum')
            ->assertJsonPath('data.rows.0.province', 'GD')
            ->assertJsonPath('data.meta.cached', false)
            ->assertJsonPath('data.meta.total', 1);

        $this->assertDatabaseHas('query_logs', [
            'user_id' => $user->id,
            'dataset_id' => $dataset->id,
            'sql' => $expectedSql,
            'row_count' => 1,
            'cached' => false,
            'status' => 'success',
        ]);

        $this->assertSame([2026, 'GD', 'BJ'], QueryLog::query()->firstOrFail()->bindings_json);
    }

    public function test_query_engine_supports_required_metric_aggregates(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $connection = $this->fakeConnection();

        $expectedSql = 'select sum(`amount`) as `amount_sum`, avg(`amount`) as `amount_avg`, count(`amount`) as `amount_count`, max(`amount`) as `amount_max`, min(`amount`) as `amount_min` from `orders` limit 10 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) [
                    'amount_sum' => '100.00',
                    'amount_avg' => '50.00',
                    'amount_count' => 2,
                    'amount_max' => '80.00',
                    'amount_min' => '20.00',
                ],
            ]);

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
                ['field' => 'amount', 'aggregate' => 'avg', 'alias' => 'amount_avg'],
                ['field' => 'amount', 'aggregate' => 'count', 'alias' => 'amount_count'],
                ['field' => 'amount', 'aggregate' => 'max', 'alias' => 'amount_max'],
                ['field' => 'amount', 'aggregate' => 'min', 'alias' => 'amount_min'],
            ],
            'limit' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.columns.0.name', 'amount_sum')
            ->assertJsonPath('data.columns.4.name', 'amount_min')
            ->assertJsonPath('data.rows.0.amount_count', 2);
    }

    public function test_query_cache_can_return_cached_results_without_reexecuting_sql(): void
    {
        Cache::flush();
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $connection = $this->fakeConnection();

        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` limit 20 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '100.00'],
            ]);

        $payload = [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
            'limit' => 20,
            'use_cache' => true,
        ];

        $this->postJson('/api/query/execute', $payload)
            ->assertOk()
            ->assertJsonPath('data.meta.cached', false);

        $this->postJson('/api/query/execute', $payload)
            ->assertOk()
            ->assertJsonPath('data.meta.cached', true)
            ->assertJsonPath('data.rows.0.province', 'GD');

        $this->assertSame(2, QueryLog::query()->count());
        $this->assertTrue(QueryLog::query()->latest('id')->firstOrFail()->cached);
    }

    public function test_query_engine_rejects_fields_outside_dataset_whitelist(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'unknown_field'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    public function test_query_execute_requires_authentication(): void
    {
        $this->postJson('/api/query/execute', [])
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
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
