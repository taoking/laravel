<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Cache\Services\CacheKeyBuilder;
use App\Modules\Chart\Models\Chart;
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

class CacheModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_chart_query_uses_cache_and_records_cached_query_log(): void
    {
        Cache::flush();
        Sanctum::actingAs(User::factory()->create());
        $chart = $this->createChart();
        $connection = $this->fakeConnection();
        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` order by `amount_sum` desc limit 10 offset 0';

        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1200.00'],
            ]);

        $this->postJson("/api/charts/{$chart->id}/data")
            ->assertOk()
            ->assertJsonPath('data.meta.cached', false);

        $this->assertNotEmpty(Cache::get(app(CacheKeyBuilder::class)->chartQueryIndex($chart->id)));

        $this->postJson("/api/charts/{$chart->id}/data")
            ->assertOk()
            ->assertJsonPath('data.meta.cached', true)
            ->assertJsonPath('data.rows.0.province', 'GD');

        $this->assertSame(2, QueryLog::query()->count());
        $this->assertTrue(QueryLog::query()->latest('id')->firstOrFail()->cached);
    }

    public function test_chart_and_dataset_updates_clear_related_chart_query_cache(): void
    {
        Cache::flush();
        Sanctum::actingAs(User::factory()->create());
        $chart = $this->createChart();
        $connection = $this->fakeConnection();
        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` order by `amount_sum` desc limit 10 offset 0';

        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1200.00'],
            ]);

        $this->postJson("/api/charts/{$chart->id}/data")
            ->assertOk()
            ->assertJsonPath('data.meta.cached', false);

        $indexKey = app(CacheKeyBuilder::class)->chartQueryIndex($chart->id);
        $this->assertNotEmpty(Cache::get($indexKey));

        $this->putJson("/api/charts/{$chart->id}", [
            'name' => 'Updated Sales Chart',
        ])
            ->assertOk();

        $this->assertNull(Cache::get($indexKey));

        $connection = $this->fakeConnection();
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'BJ', 'amount_sum' => '800.00'],
            ]);

        $this->postJson("/api/charts/{$chart->id}/data")
            ->assertOk()
            ->assertJsonPath('data.meta.cached', false);

        $this->assertNotEmpty(Cache::get($indexKey));
        $field = DatasetField::query()
            ->where('dataset_id', $chart->dataset_id)
            ->where('field_name', 'province')
            ->firstOrFail();

        $this->putJson("/api/datasets/{$chart->dataset_id}/fields/{$field->id}", [
            'display_name' => 'Province Name',
        ])
            ->assertOk();

        $this->assertNull(Cache::get($indexKey));
    }

    private function createChart(): Chart
    {
        $dataset = $this->createDataset();

        return Chart::query()->create([
            'name' => 'Sales by Province',
            'dataset_id' => $dataset->id,
            'chart_type' => 'bar',
            'config_json' => [
                'dimensions' => [
                    ['field' => 'province'],
                ],
                'metrics' => [
                    ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
                ],
                'sorts' => [
                    ['field' => 'amount_sum', 'direction' => 'desc'],
                ],
                'limit' => 10,
            ],
            'status' => 'active',
        ]);
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
