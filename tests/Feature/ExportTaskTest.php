<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Export\Models\ExportTask;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ExportTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_export_chart_data_to_csv_and_download_file(): void
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
            'tenant_id' => 10,
            'export_type' => 'csv',
            'source_type' => 'chart',
            'source_id' => $chart->id,
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.file_name', 'sales_by_province.csv')
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.created_by', $user->id)
            ->json('data.id');

        $task = ExportTask::query()->findOrFail($taskId);
        Storage::disk('minio')->assertExists($task->file_path);

        $content = Storage::disk('minio')->get($task->file_path);
        $this->assertStringContainsString("Province,Amount\n", $content);
        $this->assertStringContainsString("GD,1200.00\n", $content);

        $this->getJson("/api/export-tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.file_path', $task->file_path);

        $download = $this->get("/api/export-tasks/{$task->id}/download", ['Accept' => 'text/csv'])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('attachment; filename="sales_by_province.csv"', $download->headers->get('content-disposition'));
        $this->assertStringContainsString('GD,1200.00', $download->content());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/export-tasks/{$task->id}/download")
            ->assertForbidden()
            ->assertJsonPath('code', 40300);
    }

    public function test_failed_export_task_can_be_retried(): void
    {
        config(['filesystems.export_disk' => 'minio']);
        Storage::fake('minio');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $chart = $this->createChart();
        $task = ExportTask::query()->create([
            'export_type' => 'csv',
            'source_type' => 'chart',
            'source_id' => $chart->id,
            'status' => 'failed',
            'error_message' => 'previous failure',
            'created_by' => $user->id,
        ]);
        $connection = $this->fakeConnection();
        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` order by `amount_sum` desc limit 10 offset 0';

        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'BJ', 'amount_sum' => '800.00'],
            ]);

        $this->postJson("/api/export-tasks/{$task->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.error_message', null)
            ->assertJsonPath('data.progress', 100);

        $task->refresh();

        $this->assertSame('completed', $task->status);
        Storage::disk('minio')->assertExists($task->file_path);
        $this->assertStringContainsString('BJ,800.00', Storage::disk('minio')->get($task->file_path));
    }

    public function test_export_task_endpoints_require_authentication(): void
    {
        $this->getJson('/api/export-tasks')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
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
            'style_json' => [
                'title' => 'Sales by Province',
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
