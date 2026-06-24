<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Models\DashboardShare;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\DataPermission\Models\ResourcePermission;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Dataset\Models\DatasetTable;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Metadata\Models\MetadataUsageStat;
use App\Modules\Permission\Models\Role;
use App\Modules\Query\Models\QueryLog;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Models\Metric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetadataCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_sync_creates_assets_and_lineage(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);
        $fixture = $this->createFixture($admin);

        $this->artisan('bi:metadata:sync --all')
            ->assertExitCode(0);

        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'data_source', 'asset_id' => $fixture['data_source']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'physical_table', 'asset_id' => $fixture['table']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'physical_column', 'asset_id' => $fixture['amount_column']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'dataset', 'asset_id' => $fixture['dataset']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'dataset_field', 'asset_id' => $fixture['amount_field']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'dimension', 'asset_id' => $fixture['dimension']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'metric', 'asset_id' => $fixture['sales_metric']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'chart', 'asset_id' => $fixture['chart']->id]);
        $this->assertDatabaseHas('metadata_assets', ['asset_type' => 'dashboard', 'asset_id' => $fixture['dashboard']->id]);

        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'dataset',
            'source_asset_id' => $fixture['dataset']->id,
            'target_asset_type' => 'physical_table',
            'target_asset_id' => $fixture['table']->id,
            'relation_type' => 'depends_on',
        ]);
        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'dataset_field',
            'source_asset_id' => $fixture['amount_field']->id,
            'target_asset_type' => 'physical_column',
            'target_asset_id' => $fixture['amount_column']->id,
            'relation_type' => 'maps_to',
        ]);
        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'metric',
            'source_asset_id' => $fixture['average_metric']->id,
            'target_asset_type' => 'metric',
            'target_asset_id' => $fixture['sales_metric']->id,
            'relation_type' => 'depends_on',
        ]);
        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'chart',
            'source_asset_id' => $fixture['chart']->id,
            'target_asset_type' => 'metric',
            'target_asset_id' => $fixture['sales_metric']->id,
            'relation_type' => 'uses',
        ]);
        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'dashboard',
            'source_asset_id' => $fixture['dashboard']->id,
            'target_asset_type' => 'chart',
            'target_asset_id' => $fixture['chart']->id,
            'relation_type' => 'contains',
        ]);

        $this->getJson("/api/metadata/lineage/dataset_field/{$fixture['amount_field']->id}/downstream?depth=5")
            ->assertOk()
            ->assertJsonFragment(['id' => 'metric:'.$fixture['sales_metric']->id]);

        $this->getJson("/api/metadata/lineage/chart/{$fixture['chart']->id}/upstream")
            ->assertOk()
            ->assertJsonFragment(['target_key' => 'metric:'.$fixture['sales_metric']->id]);

        $this->getJson("/api/metadata/lineage/dataset_field/{$fixture['amount_field']->id}/graph?depth=5")
            ->assertOk()
            ->assertJsonFragment(['id' => 'chart:'.$fixture['chart']->id])
            ->assertJsonFragment(['relation' => 'uses']);

        $asset = $this->getJson("/api/metadata/assets/data_source/{$fixture['data_source']->id}")
            ->assertOk()
            ->json('data');
        $this->assertArrayNotHasKey('host', $asset['properties_json']);
        $this->assertArrayNotHasKey('username', $asset['properties_json']);
        $this->assertArrayNotHasKey('password_encrypted', $asset['properties_json']);
    }

    public function test_impact_search_and_tagging_work(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);
        $fixture = $this->createFixture($admin);
        $this->artisan('bi:metadata:sync --all')->assertExitCode(0);

        $tagId = $this->postJson('/api/metadata/tags', [
            'name' => '核心指标',
            'color' => '#dc2626',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/metadata/assets/metric/{$fixture['sales_metric']->id}/tags", [
            'tag_id' => $tagId,
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => '核心指标']);

        $this->getJson('/api/metadata/search?keyword=Sales&tag='.urlencode('核心指标'))
            ->assertOk()
            ->assertJsonFragment(['asset_type' => 'metric']);

        DashboardShare::query()->create([
            'dashboard_id' => $fixture['dashboard']->id,
            'share_token' => 'public-token',
            'share_type' => 'public',
            'created_by' => $admin->id,
        ]);

        $this->postJson('/api/metadata/impact/analyze', [
            'asset_type' => 'dataset_field',
            'asset_id' => $fixture['amount_field']->id,
            'change_type' => 'delete',
        ])
            ->assertOk()
            ->assertJsonPath('data.risk_level', 'critical')
            ->assertJsonFragment(['asset_type' => 'metric', 'asset_id' => $fixture['sales_metric']->id])
            ->assertJsonFragment(['asset_type' => 'chart', 'asset_id' => $fixture['chart']->id])
            ->assertJsonFragment(['asset_type' => 'dashboard', 'asset_id' => $fixture['dashboard']->id]);

        $this->assertDatabaseHas('impact_analysis_logs', [
            'asset_type' => 'dataset_field',
            'asset_id' => $fixture['amount_field']->id,
            'risk_level' => 'critical',
        ]);

        $this->deleteJson("/api/metadata/assets/metric/{$fixture['sales_metric']->id}/tags/{$tagId}")
            ->assertOk()
            ->assertJsonMissing(['name' => '核心指标']);
    }

    public function test_usage_stats_identify_low_frequency_and_high_slow_assets(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);
        $fixture = $this->createFixture($admin);
        $this->artisan('bi:metadata:sync --all')->assertExitCode(0);

        for ($i = 0; $i < 10; $i++) {
            QueryLog::query()->create([
                'user_id' => $admin->id,
                'dataset_id' => $fixture['dataset']->id,
                'chart_id' => $fixture['chart']->id,
                'dashboard_id' => $fixture['dashboard']->id,
                'query_hash' => 'metadata-test-'.$i,
                'sql' => 'select sum(amount) from orders',
                'elapsed_ms' => 3500,
                'row_count' => 1,
                'cached' => false,
                'is_slow' => true,
                'status' => 'success',
                'semantic_layer_used' => true,
                'semantic_metrics_json' => [
                    ['metric_code' => 'sales_amount'],
                ],
            ]);
        }

        $this->artisan('bi:metadata:usage-stats --dry-run')
            ->assertExitCode(0);
        $this->assertSame(0, MetadataUsageStat::query()->count());

        $this->artisan('bi:metadata:usage-stats')
            ->assertExitCode(0);

        $this->assertDatabaseHas('metadata_usage_stats', [
            'asset_type' => 'dataset',
            'asset_id' => $fixture['dataset']->id,
            'query_count' => 10,
            'slow_query_count' => 10,
        ]);
        $this->assertDatabaseHas('metadata_usage_stats', [
            'asset_type' => 'metric',
            'asset_id' => $fixture['sales_metric']->id,
            'query_count' => 10,
            'avg_duration_ms' => 3500,
        ]);

        $response = $this->getJson('/api/metadata/usage-stats?high_slow=1')
            ->assertOk()
            ->assertJsonFragment(['asset_type' => 'metric', 'asset_id' => $fixture['sales_metric']->id])
            ->json('data.summary');

        $this->assertNotEmpty($response['low_frequency_assets']);
        $this->assertNotEmpty($response['high_slow_assets']);
    }

    public function test_metadata_visibility_respects_dataset_permissions(): void
    {
        $owner = $this->adminUser();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);
        $fixture = $this->createFixture($owner);
        ResourcePermission::query()->create([
            'resource_type' => 'dataset',
            'resource_id' => $fixture['dataset']->id,
            'subject_type' => 'user',
            'subject_id' => $owner->id,
            'permission_type' => 'view',
        ]);

        $this->artisan('bi:metadata:sync --all')->assertExitCode(0);

        Sanctum::actingAs($other);
        $this->getJson('/api/metadata/assets?asset_type=dataset')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson("/api/metadata/assets/dataset/{$fixture['dataset']->id}")
            ->assertForbidden();
    }

    public function test_high_risk_chart_delete_requires_force_and_archives_metadata_asset(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);
        $fixture = $this->createFixture($admin);
        $this->artisan('bi:metadata:sync --all')->assertExitCode(0);

        DashboardShare::query()->create([
            'dashboard_id' => $fixture['dashboard']->id,
            'share_token' => 'public-delete-token',
            'share_type' => 'public',
            'created_by' => $admin->id,
        ]);

        $this->deleteJson("/api/charts/{$fixture['chart']->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.risk_level.0', 'critical');

        $this->assertNotSoftDeleted('charts', ['id' => $fixture['chart']->id]);

        $this->deleteJson("/api/charts/{$fixture['chart']->id}?force=1")
            ->assertOk();

        $this->assertSoftDeleted('charts', ['id' => $fixture['chart']->id]);
        $this->assertDatabaseHas('metadata_assets', [
            'asset_type' => 'chart',
            'asset_id' => $fixture['chart']->id,
            'status' => 'archived',
        ]);
    }

    public function test_metadata_lifecycle_syncs_metric_chart_and_dashboard_widget_changes(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);
        $fixture = $this->createFixture($admin);

        $metricId = $this->postJson('/api/semantic-metrics', [
            'dataset_id' => $fixture['dataset']->id,
            'name' => 'Tax Amount',
            'code' => 'tax_amount',
            'metric_type' => 'base',
            'aggregate_function' => 'sum',
            'source_field' => 'amount',
            'status' => 'active',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('metadata_assets', [
            'asset_type' => 'metric',
            'asset_id' => $metricId,
            'code' => 'tax_amount',
        ]);
        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'metric',
            'source_asset_id' => $metricId,
            'target_asset_type' => 'dataset_field',
            'target_asset_id' => $fixture['amount_field']->id,
            'relation_type' => 'depends_on',
        ]);

        $chartId = $this->postJson('/api/charts', [
            'name' => 'Tax Chart',
            'dataset_id' => $fixture['dataset']->id,
            'chart_type' => 'bar',
            'config_json' => [
                'semantic_dimensions' => [
                    ['dimension_code' => 'province'],
                ],
                'semantic_metrics' => [
                    ['metric_code' => 'tax_amount'],
                ],
            ],
        ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('metadata_assets', [
            'asset_type' => 'chart',
            'asset_id' => $chartId,
            'name' => 'Tax Chart',
        ]);
        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'chart',
            'source_asset_id' => $chartId,
            'target_asset_type' => 'metric',
            'target_asset_id' => $metricId,
            'relation_type' => 'uses',
        ]);

        $dashboardId = $this->postJson('/api/dashboards', [
            'name' => 'Tax Dashboard',
            'status' => 'active',
        ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/dashboards/{$dashboardId}/widgets", [
            'chart_id' => $chartId,
            'widget_type' => 'chart',
            'x' => 0,
            'y' => 0,
            'w' => 6,
            'h' => 4,
        ])->assertCreated();

        $this->assertDatabaseHas('metadata_assets', [
            'asset_type' => 'dashboard',
            'asset_id' => $dashboardId,
            'name' => 'Tax Dashboard',
        ]);
        $this->assertDatabaseHas('metadata_lineage_relations', [
            'source_asset_type' => 'dashboard',
            'source_asset_id' => $dashboardId,
            'target_asset_type' => 'chart',
            'target_asset_id' => $chartId,
            'relation_type' => 'contains',
        ]);
    }

    public function test_dimension_delete_endpoint_works_and_archives_metadata_asset(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);
        $fixture = $this->createFixture($admin);
        $this->artisan('bi:metadata:sync --all')->assertExitCode(0);

        $this->deleteJson("/api/dimensions/{$fixture['dimension']->id}")
            ->assertOk();

        $this->assertDatabaseMissing('dimensions', ['id' => $fixture['dimension']->id]);
        $this->assertDatabaseHas('metadata_assets', [
            'asset_type' => 'dimension',
            'asset_id' => $fixture['dimension']->id,
            'status' => 'archived',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function createFixture(User $user): array
    {
        $dataSource = DataSource::query()->create([
            'name' => 'Analytics MySQL',
            'type' => 'mysql',
            'host' => 'mysql.internal',
            'port' => 3306,
            'database_name' => 'analytics',
            'username' => 'reporter',
            'password_encrypted' => app(DataSourcePasswordEncryptor::class)->encrypt('secret'),
            'charset' => 'utf8mb4',
            'timezone' => '+00:00',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $table = DataSourceTable::query()->create([
            'data_source_id' => $dataSource->id,
            'table_name' => 'orders',
            'table_comment' => 'Orders fact table',
            'table_type' => 'base table',
            'row_count_estimate' => 1000,
            'synced_at' => now(),
        ]);
        $amountColumn = $this->createPhysicalColumn($dataSource, $table, 'amount', 'decimal', 2);
        $orderColumn = $this->createPhysicalColumn($dataSource, $table, 'order_id', 'integer', 1);
        $provinceColumn = $this->createPhysicalColumn($dataSource, $table, 'province', 'string', 3);
        $dataset = Dataset::query()->create([
            'name' => 'Orders Dataset',
            'description' => 'Order analysis',
            'data_source_id' => $dataSource->id,
            'dataset_type' => 'single_table',
            'main_table' => 'orders',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        DatasetTable::query()->create([
            'dataset_id' => $dataset->id,
            'data_source_id' => $dataSource->id,
            'table_name' => 'orders',
            'alias' => 'o',
            'sort_order' => 1,
        ]);
        $provinceField = $this->createDatasetField($dataset, 'province', 'Province', 'string', true, false, 1);
        $amountField = $this->createDatasetField($dataset, 'amount', 'Amount', 'decimal', false, true, 2);
        $orderField = $this->createDatasetField($dataset, 'order_id', 'Order ID', 'integer', false, true, 3);
        $dimension = Dimension::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Province',
            'code' => 'province',
            'field_name' => 'province',
            'dimension_type' => 'region',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $salesMetric = Metric::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Sales Amount',
            'code' => 'sales_amount',
            'metric_type' => 'base',
            'aggregate_function' => 'sum',
            'source_field' => 'amount',
            'status' => 'active',
            'version' => 1,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $orderMetric = Metric::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Order Count',
            'code' => 'order_count',
            'metric_type' => 'base',
            'aggregate_function' => 'count',
            'source_field' => 'order_id',
            'status' => 'active',
            'version' => 1,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $averageMetric = Metric::query()->create([
            'dataset_id' => $dataset->id,
            'name' => 'Average Order Amount',
            'code' => 'avg_order_amount',
            'metric_type' => 'compound',
            'aggregate_function' => 'expression',
            'formula' => 'sales_amount / order_count',
            'status' => 'active',
            'version' => 1,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $chart = Chart::query()->create([
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
                'dimensions' => [
                    ['field' => 'province'],
                ],
                'metrics' => [
                    ['field' => 'amount'],
                ],
            ],
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $dashboard = Dashboard::query()->create([
            'name' => 'Sales Dashboard',
            'description' => 'Sales overview',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        DashboardWidget::query()->create([
            'dashboard_id' => $dashboard->id,
            'chart_id' => $chart->id,
            'widget_type' => 'chart',
            'x' => 0,
            'y' => 0,
            'w' => 6,
            'h' => 4,
            'sort_order' => 1,
        ]);

        return [
            'data_source' => $dataSource,
            'table' => $table,
            'amount_column' => $amountColumn,
            'order_column' => $orderColumn,
            'province_column' => $provinceColumn,
            'dataset' => $dataset,
            'province_field' => $provinceField,
            'amount_field' => $amountField,
            'order_field' => $orderField,
            'dimension' => $dimension,
            'sales_metric' => $salesMetric,
            'order_metric' => $orderMetric,
            'average_metric' => $averageMetric,
            'chart' => $chart,
            'dashboard' => $dashboard,
        ];
    }

    private function createPhysicalColumn(DataSource $dataSource, DataSourceTable $table, string $name, string $type, int $position): DataSourceField
    {
        return DataSourceField::query()->create([
            'data_source_id' => $dataSource->id,
            'table_id' => $table->id,
            'table_name' => $table->table_name,
            'field_name' => $name,
            'field_comment' => ucfirst($name),
            'data_type' => $type,
            'normalized_type' => $type,
            'is_nullable' => false,
            'is_primary_key' => $name === 'order_id',
            'ordinal_position' => $position,
        ]);
    }

    private function createDatasetField(Dataset $dataset, string $name, string $displayName, string $type, bool $dimension, bool $metric, int $sortOrder): DatasetField
    {
        return DatasetField::query()->create([
            'dataset_id' => $dataset->id,
            'table_name' => 'orders',
            'field_name' => $name,
            'field_alias' => $name,
            'display_name' => $displayName,
            'source_type' => 'physical',
            'normalized_type' => $type,
            'semantic_type' => $dimension ? 'normal' : 'measure',
            'is_dimension' => $dimension,
            'is_metric' => $metric,
            'is_visible' => true,
            'is_filterable' => true,
            'default_aggregate' => $metric ? 'sum' : 'none',
            'sort_order' => $sortOrder,
        ]);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate([
            'code' => 'admin',
        ], [
            'name' => 'Administrator',
            'is_system' => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }
}
