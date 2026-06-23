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
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class OlapDataSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_starrocks_and_doris_data_sources_can_be_created_with_default_ports(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/data-sources', [
            'name' => 'StarRocks Warehouse',
            'type' => 'starrocks',
            'host' => 'starrocks-fe.example.test',
            'database_name' => 'analytics',
            'username' => 'reporter',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'starrocks')
            ->assertJsonPath('data.port', 9030)
            ->assertJsonMissingPath('data.password_encrypted');

        $this->postJson('/api/data-sources', [
            'name' => 'Doris Warehouse',
            'type' => 'doris',
            'host' => 'doris-fe.example.test',
            'database_name' => 'analytics',
            'username' => 'reporter',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'doris')
            ->assertJsonPath('data.port', 9030);
    }

    public function test_olap_metadata_and_materialized_view_endpoints_work_through_mysql_protocol_driver(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataSource = $this->createDataSource('starrocks');
        $connection = $this->fakeConnection();

        $connection
            ->shouldReceive('select')
            ->once()
            ->with('show databases')
            ->andReturn([
                (object) ['Database' => 'analytics'],
            ]);

        $this->getJson("/api/data-sources/{$dataSource->id}/databases")
            ->assertOk()
            ->assertJsonPath('data.0.database_name', 'analytics');

        $connection
            ->shouldReceive('select')
            ->once()
            ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, 'from information_schema.TABLES')), ['analytics'])
            ->andReturn([
                (object) [
                    'database_name' => 'analytics',
                    'name' => 'mv_daily_sales',
                    'table_name' => 'mv_daily_sales',
                    'table_type' => 'MATERIALIZED VIEW',
                    'comment' => 'daily sales materialized view',
                ],
            ]);

        $this->getJson("/api/data-sources/{$dataSource->id}/materialized-views")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'mv_daily_sales');

        $connection
            ->shouldReceive('statement')
            ->once()
            ->with('refresh materialized view `mv_daily_sales`')
            ->andReturn(true);

        $this->postJson("/api/data-sources/{$dataSource->id}/materialized-views/mv_daily_sales/refresh")
            ->assertOk()
            ->assertJsonPath('data.refreshed', true);
    }

    public function test_starrocks_query_execution_logs_engine_type_and_data_source_type(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset('starrocks');
        $connection = $this->fakeConnection();

        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` limit 50 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
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
            'limit' => 50,
        ])
            ->assertOk()
            ->assertJsonPath('data.meta.engine_type', 'starrocks')
            ->assertJsonPath('data.meta.data_source_type', 'starrocks')
            ->assertJsonPath('data.rows.0.province', 'GD');

        $log = QueryLog::query()->firstOrFail();

        $this->assertSame('starrocks', $log->engine_type);
        $this->assertSame('starrocks', $log->data_source_type);
        $this->assertSame($expectedSql, $log->sql);
    }

    public function test_dataset_explain_generates_backend_sql_and_returns_explain_rows(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset('doris');
        $connection = $this->fakeConnection();

        $expectedSql = 'select `province` as `province`, count(`amount`) as `amount_count` from `orders` where `year` = ? group by `province` limit 20 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with('explain '.$expectedSql, [2026])
            ->andReturn([
                (object) ['PLAN' => 'OLAP_SCAN_NODE'],
            ]);

        $this->postJson("/api/datasets/{$dataset->id}/explain", [
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'count', 'alias' => 'amount_count'],
            ],
            'filters' => [
                ['field' => 'year', 'operator' => '=', 'value' => 2026],
            ],
            'limit' => 20,
        ])
            ->assertOk()
            ->assertJsonPath('data.engine_type', 'doris')
            ->assertJsonPath('data.generated_sql', $expectedSql)
            ->assertJsonPath('data.explain_result.0.PLAN', 'OLAP_SCAN_NODE')
            ->assertJsonPath('data.warnings', []);
    }

    private function createDataset(string $type): Dataset
    {
        $dataSource = $this->createDataSource($type);

        $dataset = Dataset::query()->create([
            'name' => ucfirst($type).' Orders Dataset',
            'data_source_id' => $dataSource->id,
            'dataset_type' => 'single_table',
            'main_table' => 'orders',
            'status' => 'active',
        ]);

        foreach ([
            ['field_name' => 'province', 'display_name' => 'Province', 'normalized_type' => 'string', 'semantic_type' => 'province', 'is_dimension' => true, 'is_metric' => false, 'default_aggregate' => 'none', 'sort_order' => 1],
            ['field_name' => 'amount', 'display_name' => 'Amount', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'default_aggregate' => 'sum', 'sort_order' => 2],
            ['field_name' => 'year', 'display_name' => 'Year', 'normalized_type' => 'integer', 'semantic_type' => 'time', 'is_dimension' => true, 'is_metric' => false, 'default_aggregate' => 'none', 'sort_order' => 3],
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

    private function createDataSource(string $type): DataSource
    {
        return DataSource::query()->create([
            'name' => ucfirst($type).' Warehouse',
            'type' => $type,
            'host' => $type.'-fe',
            'port' => 9030,
            'database_name' => 'analytics',
            'username' => 'reporter',
            'password_encrypted' => app(DataSourcePasswordEncryptor::class)->encrypt('secret'),
            'charset' => 'utf8mb4',
            'timezone' => '+00:00',
            'status' => 'active',
        ]);
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
