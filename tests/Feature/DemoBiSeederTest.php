<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\Query\Models\QueryLog;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Models\Metric;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DemoBiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_command_creates_idempotent_demo_assets(): void
    {
        $this->artisan('bi:demo:seed --orders=120 --skip-large-data')
            ->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('sales_orders'));
        $this->assertSame(120, DB::table('sales_orders')->count());
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'analyst@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'viewer@example.com']);
        $this->assertDatabaseHas('data_sources', ['name' => 'Demo MySQL Sales']);
        $this->assertDatabaseHas('datasets', ['name' => '销售订单数据集', 'main_table' => 'sales_orders']);
        $this->assertSame(10, Chart::query()->whereIn('name', [
            '销售额指标卡',
            '订单数指标卡',
            '利润率指标卡',
            '最近 12 个月销售趋势折线图',
            '省份销售额柱状图',
            '产品分类销售额饼图',
            '销售渠道订单数柱状图',
            '客户类型销售额柱状图',
            '城市销售额排行表格',
            '销售明细表格',
        ])->count());
        $this->assertSame(9, Metric::query()->whereIn('code', [
            'sales_amount',
            'order_count',
            'total_quantity',
            'refund_amount_sum',
            'cost_amount_sum',
            'profit_amount_sum',
            'avg_order_amount',
            'profit_rate',
            'refund_rate',
        ])->count());
        $this->assertSame(10, Dimension::query()->where('dataset_id', Dataset::query()->where('name', '销售订单数据集')->value('id'))->count());
        $this->assertDatabaseHas('dashboards', ['name' => '电商销售分析看板']);
        $this->assertSame(9, DashboardWidget::query()->where('dashboard_id', Dashboard::query()->where('name', '电商销售分析看板')->value('id'))->count());

        $this->artisan('bi:demo:seed --orders=120 --skip-large-data')
            ->assertExitCode(0);

        $this->assertSame(120, DB::table('sales_orders')->count());
        $this->assertSame(1, DataSource::query()->where('name', 'Demo MySQL Sales')->count());
        $this->assertSame(1, Dataset::query()->where('name', '销售订单数据集')->count());
        $this->assertSame(1, Dashboard::query()->where('name', '电商销售分析看板')->count());
    }

    public function test_demo_chart_query_and_viewer_permissions_work_through_existing_api(): void
    {
        $this->artisan('bi:demo:seed --orders=120 --skip-large-data')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $viewer = User::query()->where('email', 'viewer@example.com')->firstOrFail();
        $salesCard = Chart::query()->where('name', '销售额指标卡')->firstOrFail();
        $dataset = Dataset::query()->where('name', '销售订单数据集')->firstOrFail();
        $connection = $this->fakeConnection();

        $connection
            ->shouldReceive('select')
            ->once()
            ->with('select sum(`amount`) as `amount_sum` from `sales_orders` limit 1 offset 0', [])
            ->andReturn([
                (object) ['amount_sum' => '100000.00'],
            ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/charts/{$salesCard->id}/data", [])
            ->assertOk()
            ->assertJsonPath('data.rows.0.amount_sum', '100000.00')
            ->assertJsonPath('data.meta.request_source', 'chart');

        $this->assertDatabaseHas('query_logs', [
            'dataset_id' => $dataset->id,
            'chart_id' => $salesCard->id,
            'status' => 'success',
        ]);

        $connection
            ->shouldReceive('select')
            ->once()
            ->with('select `province` as `province`, sum(`amount`) as `amount_sum` from `sales_orders` where `province` = ? group by `province` limit 10 offset 0', ['广东'])
            ->andReturn([
                (object) ['province' => '广东', 'amount_sum' => '8800.00'],
            ]);

        Sanctum::actingAs($viewer);
        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
            ],
            'limit' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.rows.0.province', '广东');

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'raw_fields' => ['customer_name'],
            'limit' => 10,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);

        $this->assertGreaterThanOrEqual(2, QueryLog::query()->count());
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
