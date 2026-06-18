<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
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

class ChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_supported_chart_types_and_manage_chart(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);
        $dataset = $this->createDataset();

        $configs = [
            'metric_card' => [
                'metrics' => [
                    ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
                ],
                'limit' => 1,
            ],
            'bar' => $this->groupedChartConfig(),
            'line' => $this->groupedChartConfig(),
            'pie' => $this->groupedChartConfig(),
            'table' => [
                'dimensions' => [
                    ['field' => 'province'],
                ],
                'metrics' => [
                    ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
                ],
                'limit' => 20,
            ],
        ];

        $chartIds = [];

        foreach ($configs as $chartType => $config) {
            $chartIds[] = $this->postJson('/api/charts', [
                'name' => ucfirst($chartType).' Chart',
                'description' => 'Chart config test',
                'dataset_id' => $dataset->id,
                'chart_type' => $chartType,
                'config_json' => $config,
                'style_json' => [
                    'title' => ucfirst($chartType).' Chart',
                    'legend' => true,
                ],
            ])
                ->assertCreated()
                ->assertJsonPath('code', 0)
                ->assertJsonPath('data.chart_type', $chartType)
                ->assertJsonPath('data.created_by', $actor->id)
                ->json('data.id');
        }

        $this->assertSame(5, Chart::query()->count());

        $firstChartId = $chartIds[0];

        $this->putJson("/api/charts/{$firstChartId}", [
            'name' => 'Updated Metric Card',
            'status' => 'disabled',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Metric Card')
            ->assertJsonPath('data.status', 'disabled');

        $this->deleteJson("/api/charts/{$firstChartId}")
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSoftDeleted('charts', [
            'id' => $firstChartId,
        ]);
    }

    public function test_chart_data_endpoint_calls_query_engine_with_saved_config_and_overrides(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset();

        $chart = Chart::query()->create([
            'name' => 'Sales by Province',
            'dataset_id' => $dataset->id,
            'chart_type' => 'bar',
            'config_json' => $this->groupedChartConfig(),
            'style_json' => [
                'title' => 'Sales by Province',
            ],
            'status' => 'active',
        ]);

        $connection = $this->fakeConnection();
        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` where `province` = ? group by `province` order by `amount_sum` desc limit 5 offset 0';

        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, ['GD'])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1200.00'],
            ]);

        $this->postJson("/api/charts/{$chart->id}/data", [
            'filters' => [
                ['field' => 'province', 'operator' => '=', 'value' => 'GD'],
            ],
            'limit' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.columns.0.name', 'province')
            ->assertJsonPath('data.columns.1.name', 'amount_sum')
            ->assertJsonPath('data.rows.0.amount_sum', '1200.00')
            ->assertJsonPath('data.meta.cached', false);

        $this->assertDatabaseHas('query_logs', [
            'user_id' => $user->id,
            'dataset_id' => $dataset->id,
            'sql' => $expectedSql,
            'row_count' => 1,
            'status' => 'success',
        ]);
        $this->assertSame(['GD'], QueryLog::query()->firstOrFail()->bindings_json);
    }

    public function test_chart_preview_returns_query_data_without_persisting_chart(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();
        $connection = $this->fakeConnection();

        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` order by `amount_sum` desc limit 10 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'BJ', 'amount_sum' => '800.00'],
            ]);

        $this->postJson('/api/charts/preview', [
            'dataset_id' => $dataset->id,
            'chart_type' => 'line',
            'config_json' => $this->groupedChartConfig(),
            'style_json' => [
                'title' => 'Preview',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.rows.0.province', 'BJ')
            ->assertJsonPath('data.meta.total', 1);

        $this->assertSame(0, Chart::query()->count());
    }

    public function test_chart_config_rejects_fields_outside_dataset_whitelist(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $dataset = $this->createDataset();

        $this->postJson('/api/charts', [
            'name' => 'Invalid Chart',
            'dataset_id' => $dataset->id,
            'chart_type' => 'bar',
            'config_json' => [
                'dimensions' => [
                    ['field' => 'unknown_field'],
                ],
                'metrics' => [
                    ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    public function test_chart_endpoints_require_authentication(): void
    {
        $this->getJson('/api/charts')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
    }

    /**
     * @return array<string, mixed>
     */
    private function groupedChartConfig(): array
    {
        return [
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
        ];
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
