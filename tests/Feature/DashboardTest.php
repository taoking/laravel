<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Models\DashboardShare;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_dashboard_widgets_and_layout(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);
        [$chartA, $chartB] = $this->createCharts();

        $dashboardId = $this->postJson('/api/dashboards', [
            'name' => 'Executive Dashboard',
            'description' => 'Sales overview',
            'global_filters_json' => [
                ['field' => 'year', 'operator' => '=', 'value' => 2026],
            ],
            'filters' => [
                [
                    'field_name' => 'province',
                    'label' => 'Province',
                    'filter_type' => 'select',
                    'config_json' => ['operator' => '='],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.name', 'Executive Dashboard')
            ->assertJsonPath('data.created_by', $actor->id)
            ->assertJsonPath('data.filters.0.field_name', 'province')
            ->json('data.id');

        $widgetA = $this->postJson("/api/dashboards/{$dashboardId}/widgets", [
            'chart_id' => $chartA->id,
            'x' => 0,
            'y' => 0,
            'w' => 6,
            'h' => 4,
            'sort_order' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.chart_id', $chartA->id)
            ->json('data.id');

        $this->postJson("/api/dashboards/{$dashboardId}/widgets", [
            'chart_id' => $chartB->id,
            'x' => 6,
            'y' => 0,
            'w' => 6,
            'h' => 4,
            'sort_order' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.chart_id', $chartB->id);

        $this->putJson("/api/dashboards/{$dashboardId}/widgets/{$widgetA}", [
            'x' => 0,
            'y' => 4,
            'w' => 12,
            'h' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.w', 12)
            ->assertJsonPath('data.h', 5);

        $this->getJson("/api/dashboards/{$dashboardId}")
            ->assertOk()
            ->assertJsonPath('data.widgets_count', 2)
            ->assertJsonFragment([
                'widget_id' => $widgetA,
                'x' => 0,
                'y' => 4,
                'w' => 12,
                'h' => 5,
            ]);

        $this->assertSame(2, DashboardWidget::query()->where('dashboard_id', $dashboardId)->count());
    }

    public function test_dashboard_data_refreshes_all_widget_charts_with_global_filters(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$chartA, $chartB] = $this->createCharts();
        $connection = $this->fakeConnection();
        $dashboard = Dashboard::query()->create([
            'name' => 'Sales Dashboard',
            'global_filters_json' => [
                ['field' => 'year', 'operator' => '=', 'value' => 2026],
            ],
            'status' => 'active',
        ]);

        $dashboard->widgets()->create([
            'chart_id' => $chartA->id,
            'x' => 0,
            'y' => 0,
            'w' => 6,
            'h' => 4,
            'sort_order' => 1,
        ]);
        $dashboard->widgets()->create([
            'chart_id' => $chartB->id,
            'x' => 6,
            'y' => 0,
            'w' => 6,
            'h' => 4,
            'sort_order' => 2,
        ]);

        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` where `year` = ? and `province` = ? group by `province` order by `amount_sum` desc limit 10 offset 0';
        $connection
            ->shouldReceive('select')
            ->twice()
            ->with($expectedSql, [2026, 'GD'])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1200.00'],
            ]);

        $this->postJson("/api/dashboards/{$dashboard->id}/data", [
            'filters' => [
                ['field' => 'province', 'operator' => '=', 'value' => 'GD'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.dashboard_id', $dashboard->id)
            ->assertJsonPath('data.filters.0.field', 'year')
            ->assertJsonPath('data.filters.1.field', 'province')
            ->assertJsonPath('data.widgets.0.data.rows.0.province', 'GD')
            ->assertJsonPath('data.widgets.1.data.rows.0.amount_sum', '1200.00');
    }

    public function test_dashboard_can_be_shared_and_read_publicly(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);
        [$chart] = $this->createCharts();

        $dashboard = Dashboard::query()->create([
            'name' => 'Shared Dashboard',
            'status' => 'active',
            'created_by' => $actor->id,
        ]);
        $dashboard->widgets()->create([
            'chart_id' => $chart->id,
            'x' => 0,
            'y' => 0,
            'w' => 6,
            'h' => 4,
        ]);

        $token = $this->postJson("/api/dashboards/{$dashboard->id}/share", [
            'share_type' => 'public',
        ])
            ->assertCreated()
            ->assertJsonPath('data.share_type', 'public')
            ->assertJsonMissingPath('data.password_hash')
            ->json('data.share_token');

        $this->assertSame(1, DashboardShare::query()->count());

        $this->getJson("/api/share/dashboards/{$token}")
            ->assertOk()
            ->assertJsonPath('data.dashboard.name', 'Shared Dashboard')
            ->assertJsonPath('data.dashboard.widgets.0.chart_id', $chart->id);
    }

    public function test_dashboard_widget_must_belong_to_dashboard_when_updating(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$chartA, $chartB] = $this->createCharts();

        $dashboardA = Dashboard::query()->create(['name' => 'A']);
        $dashboardB = Dashboard::query()->create(['name' => 'B']);
        $widget = $dashboardA->widgets()->create([
            'chart_id' => $chartA->id,
            'x' => 0,
            'y' => 0,
            'w' => 6,
            'h' => 4,
        ]);

        $this->putJson("/api/dashboards/{$dashboardB->id}/widgets/{$widget->id}", [
            'chart_id' => $chartB->id,
        ])
            ->assertNotFound();
    }

    public function test_dashboard_endpoints_require_authentication(): void
    {
        $this->getJson('/api/dashboards')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
    }

    /**
     * @return array<int, Chart>
     */
    private function createCharts(): array
    {
        $dataset = $this->createDataset();

        return [
            Chart::query()->create([
                'name' => 'Sales by Province',
                'dataset_id' => $dataset->id,
                'chart_type' => 'bar',
                'config_json' => $this->chartConfig(),
                'style_json' => ['title' => 'Sales by Province'],
                'status' => 'active',
            ]),
            Chart::query()->create([
                'name' => 'Sales Line',
                'dataset_id' => $dataset->id,
                'chart_type' => 'line',
                'config_json' => $this->chartConfig(),
                'style_json' => ['title' => 'Sales Line'],
                'status' => 'active',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function chartConfig(): array
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
