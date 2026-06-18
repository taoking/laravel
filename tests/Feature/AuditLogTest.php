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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_logs_can_be_filtered_by_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/charts')
            ->assertOk();

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $user->id,
            'request_method' => 'GET',
            'resource_type' => 'charts',
            'response_code' => 200,
        ]);

        $this->getJson("/api/operation-logs?user_id={$user->id}")
            ->assertOk()
            ->assertJsonFragment([
                'resource_type' => 'charts',
                'response_code' => 200,
            ]);
    }

    public function test_login_logs_record_success_and_failure(): void
    {
        $user = User::factory()->create([
            'email' => 'audit@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'audit@example.com',
            'password' => 'secret-password',
        ])
            ->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'audit@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'status' => 'failed',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/login-logs?status=failed')
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'failed');
    }

    public function test_chart_query_logs_include_chart_context_and_are_listable(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
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

        $queryLog = QueryLog::query()->firstOrFail();

        $this->assertSame($chart->id, $queryLog->chart_id);
        $this->assertFalse($queryLog->is_slow);

        $this->getJson("/api/query-logs?chart_id={$chart->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.chart_id', $chart->id)
            ->assertJsonPath('data.items.0.cached', false);
    }

    public function test_export_logs_are_written_when_export_task_finishes(): void
    {
        config(['filesystems.export_disk' => 'minio']);
        Storage::fake('minio');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
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

        $taskId = $this->postJson('/api/export-tasks', [
            'export_type' => 'csv',
            'source_type' => 'chart',
            'source_id' => $chart->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->json('data.id');

        $this->assertDatabaseHas('export_logs', [
            'user_id' => $user->id,
            'export_task_id' => $taskId,
            'source_type' => 'chart',
            'status' => 'completed',
        ]);

        $this->getJson('/api/export-logs?status=completed')
            ->assertOk()
            ->assertJsonPath('data.items.0.export_task_id', $taskId);
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
