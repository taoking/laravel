<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Permission\Models\Role;
use App\Modules\Query\Models\QueryLog;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Models\MetricDependency;
use App\Modules\Semantic\Models\MetricUsage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SemanticLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_metric_categories_dimensions_and_metric_initializers_work(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset($user);

        $categoryId = $this->postJson('/api/metric-categories', [
            'name' => 'Sales Metrics',
            'sort_order' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sales Metrics')
            ->json('data.id');

        $this->postJson("/api/datasets/{$dataset->id}/dimensions/init-from-fields")
            ->assertOk()
            ->assertJsonPath('data.0.code', 'province');

        $this->postJson("/api/datasets/{$dataset->id}/metrics/init-from-fields")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'draft');

        $this->assertDatabaseHas('metric_categories', ['id' => $categoryId, 'name' => 'Sales Metrics']);
        $this->assertDatabaseHas('dimensions', ['dataset_id' => $dataset->id, 'code' => 'province']);
        $this->assertDatabaseHas('metrics', ['dataset_id' => $dataset->id, 'code' => 'amount_sum']);
    }

    public function test_base_and_compound_metrics_create_versions_dependencies_and_impact(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset($user);
        $sales = $this->createMetric('sales_amount', $dataset, 'amount', 'sum');
        $orders = $this->createMetric('order_count', $dataset, 'order_id', 'count');

        $compoundId = $this->postJson('/api/semantic-metrics', [
            'dataset_id' => $dataset->id,
            'name' => 'Average Order Amount',
            'code' => 'avg_order_amount',
            'metric_type' => 'compound',
            'aggregate_function' => 'expression',
            'formula' => 'sales_amount / order_count',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->json('data.id');

        $this->assertDatabaseHas('metric_versions', [
            'metric_id' => $compoundId,
            'version' => 1,
        ]);
        $this->assertSame(2, MetricDependency::query()->where('metric_id', $compoundId)->count());

        $this->postJson('/api/semantic-metrics/validate-formula', [
            'dataset_id' => $dataset->id,
            'formula' => 'sales_amount / order_count',
        ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.dependencies.0', 'sales_amount');

        $this->putJson("/api/semantic-metrics/{$compoundId}", [
            'description' => 'Updated definition.',
        ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->getJson("/api/semantic-metrics/{$compoundId}/versions")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/semantic-metrics/{$compoundId}/dependencies")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertNotNull($sales);
        $this->assertNotNull($orders);
    }

    public function test_cycle_dependencies_are_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset($user);
        $sales = $this->createMetric('sales_amount', $dataset, 'amount', 'sum');
        $ratioId = $this->postJson('/api/semantic-metrics', [
            'dataset_id' => $dataset->id,
            'name' => 'Ratio',
            'code' => 'ratio_metric',
            'metric_type' => 'compound',
            'aggregate_function' => 'expression',
            'formula' => 'sales_amount / 10',
            'status' => 'active',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('metric_dependencies', [
            'metric_id' => $ratioId,
            'depends_on_metric_id' => $sales->id,
            'dependency_type' => 'metric',
        ]);

        $this->putJson("/api/semantic-metrics/{$sales->id}", [
            'metric_type' => 'compound',
            'aggregate_function' => 'expression',
            'source_field' => null,
            'formula' => 'ratio_metric + 1',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    public function test_semantic_query_compiles_executes_and_logs_semantic_context(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset($user);
        $this->postJson('/api/dimensions', [
            'dataset_id' => $dataset->id,
            'name' => 'Province',
            'code' => 'province',
            'field_name' => 'province',
            'dimension_type' => 'region',
            'status' => 'active',
        ])->assertCreated();
        $this->createMetric('sales_amount', $dataset, 'amount', 'sum');
        $this->createMetric('order_count', $dataset, 'order_id', 'count');
        $this->postJson('/api/semantic-metrics', [
            'dataset_id' => $dataset->id,
            'name' => 'Average Order Amount',
            'code' => 'avg_order_amount',
            'metric_type' => 'compound',
            'aggregate_function' => 'expression',
            'formula' => 'sales_amount / order_count',
            'status' => 'active',
        ])->assertCreated();

        $connection = $this->fakeConnection();
        $expectedSql = 'select `province` as `province`, sum(`amount`) as `sales_amount`, count(`order_id`) as `order_count` from `orders` group by `province` limit 100 offset 0';
        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'GD', 'sales_amount' => 100, 'order_count' => 4],
            ]);

        $this->postJson('/api/query/execute', [
            'dataset_id' => $dataset->id,
            'semantic_dimensions' => [
                ['dimension_code' => 'province'],
            ],
            'semantic_metrics' => [
                ['metric_code' => 'sales_amount'],
                ['metric_code' => 'avg_order_amount'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.columns.0.name', 'province')
            ->assertJsonPath('data.columns.1.name', 'sales_amount')
            ->assertJsonPath('data.columns.2.name', 'avg_order_amount')
            ->assertJsonPath('data.rows.0.avg_order_amount', 25)
            ->assertJsonMissingPath('data.rows.0.order_count')
            ->assertJsonPath('data.meta.semantic_layer_used', true);

        $log = QueryLog::query()->firstOrFail();
        $this->assertTrue($log->semantic_layer_used);
        $this->assertSame(['sales_amount' => 1, 'avg_order_amount' => 1, 'order_count' => 1], $log->metric_versions_json);
    }

    public function test_chart_save_records_metric_usage_and_impact(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset($user);
        $this->postJson('/api/dimensions', [
            'dataset_id' => $dataset->id,
            'name' => 'Province',
            'code' => 'province',
            'field_name' => 'province',
            'dimension_type' => 'region',
            'status' => 'active',
        ])->assertCreated();
        $metric = $this->createMetric('sales_amount', $dataset, 'amount', 'sum');

        $chartId = $this->postJson('/api/charts', [
            'name' => 'Sales Chart',
            'dataset_id' => $dataset->id,
            'chart_type' => 'bar',
            'config_json' => [
                'semantic_dimensions' => [
                    ['dimension_code' => 'province'],
                ],
                'semantic_metrics' => [
                    ['metric_code' => 'sales_amount'],
                ],
                'limit' => 100,
            ],
        ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('metric_usages', [
            'metric_id' => $metric->id,
            'used_by_type' => 'chart',
            'used_by_id' => $chartId,
            'metric_version' => 1,
        ]);

        $this->getJson("/api/semantic-metrics/{$metric->id}/impact")
            ->assertOk()
            ->assertJsonPath('data.used_by_charts', 1)
            ->assertJsonPath('data.risk_level', 'medium');

        $this->assertSame(1, MetricUsage::query()->count());
        $this->assertNotNull(Chart::query()->find($chartId));
    }

    public function test_semantic_management_permissions_follow_dataset_owner_and_admin(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create();
        $adminRole = Role::query()->create([
            'name' => 'Administrator',
            'code' => 'admin',
            'is_system' => true,
        ]);
        $admin->roles()->attach($adminRole->id);

        Sanctum::actingAs($owner);
        $dataset = $this->createDataset($owner);
        $draftMetricId = $this->postJson('/api/semantic-metrics', [
            'dataset_id' => $dataset->id,
            'name' => 'Draft Sales',
            'code' => 'draft_sales',
            'metric_type' => 'base',
            'aggregate_function' => 'sum',
            'source_field' => 'amount',
            'status' => 'draft',
        ])
            ->assertCreated()
            ->json('data.id');
        $dimensionId = $this->postJson('/api/dimensions', [
            'dataset_id' => $dataset->id,
            'name' => 'Province',
            'code' => 'province',
            'field_name' => 'province',
            'dimension_type' => 'region',
            'status' => 'active',
        ])
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($otherUser);
        $this->getJson("/api/semantic-metrics/{$draftMetricId}")
            ->assertForbidden();
        $this->putJson("/api/semantic-metrics/{$draftMetricId}", [
            'description' => 'Unauthorized edit.',
        ])
            ->assertForbidden();
        $this->putJson("/api/dimensions/{$dimensionId}", [
            'description' => 'Unauthorized dimension edit.',
        ])
            ->assertForbidden();
        $this->postJson('/api/semantic-metrics/validate-formula', [
            'dataset_id' => $dataset->id,
            'formula' => 'draft_sales / 10',
        ])
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/semantic-metrics/{$draftMetricId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        Sanctum::actingAs($otherUser);
        $this->getJson("/api/semantic-metrics/{$draftMetricId}")
            ->assertOk()
            ->assertJsonPath('data.code', 'draft_sales');

        Sanctum::actingAs($admin);
        $this->putJson("/api/semantic-metrics/{$draftMetricId}", [
            'description' => 'Admin edit.',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Admin edit.');
    }

    private function createDataset(User $user): Dataset
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
            'created_by' => $user->id,
        ]);

        foreach ([
            ['field_name' => 'province', 'display_name' => 'Province', 'normalized_type' => 'string', 'semantic_type' => 'province', 'is_dimension' => true, 'is_metric' => false, 'default_aggregate' => 'none', 'sort_order' => 1],
            ['field_name' => 'amount', 'display_name' => 'Amount', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'default_aggregate' => 'sum', 'sort_order' => 2],
            ['field_name' => 'order_id', 'display_name' => 'Order ID', 'normalized_type' => 'integer', 'semantic_type' => 'id', 'is_dimension' => false, 'is_metric' => true, 'default_aggregate' => 'count', 'sort_order' => 3],
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

    private function createMetric(string $code, Dataset $dataset, string $field, string $aggregate): Metric
    {
        $id = $this->postJson('/api/semantic-metrics', [
            'dataset_id' => $dataset->id,
            'name' => str_replace('_', ' ', ucfirst($code)),
            'code' => $code,
            'metric_type' => 'base',
            'aggregate_function' => $aggregate,
            'source_field' => $field,
            'status' => 'active',
        ])
            ->assertCreated()
            ->json('data.id');

        return Metric::query()->findOrFail($id);
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
