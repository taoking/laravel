<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Drivers\DatabaseDriverInterface;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourceDriverManager;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DatasetTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_single_table_dataset_and_sync_fields(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);
        $this->fakeMetadataDriver();

        $dataSource = $this->createDataSource();

        $datasetId = $this->postJson('/api/datasets', [
            'name' => 'Orders Dataset',
            'description' => 'Single table dataset',
            'data_source_id' => $dataSource->id,
            'dataset_type' => 'single_table',
            'main_table' => 'orders',
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.name', 'Orders Dataset')
            ->assertJsonPath('data.main_table', 'orders')
            ->assertJsonPath('data.created_by', $actor->id)
            ->assertJsonPath('data.fields.0.field_name', 'id')
            ->assertJsonPath('data.fields.1.field_name', 'amount')
            ->json('data.id');

        $this->assertDatabaseHas('dataset_tables', [
            'dataset_id' => $datasetId,
            'data_source_id' => $dataSource->id,
            'table_name' => 'orders',
        ]);

        $this->assertDatabaseHas('dataset_fields', [
            'dataset_id' => $datasetId,
            'table_name' => 'orders',
            'field_name' => 'amount',
            'is_metric' => true,
            'default_aggregate' => 'sum',
        ]);

        $this->assertSame(3, DatasetField::query()->where('dataset_id', $datasetId)->count());
    }

    public function test_dataset_field_configuration_can_be_updated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->fakeMetadataDriver();

        $dataset = $this->createDatasetWithSyncedFields();
        $amountField = $dataset->fields()->where('field_name', 'amount')->firstOrFail();

        $this->putJson("/api/datasets/{$dataset->id}/fields/{$amountField->id}", [
            'field_alias' => 'total_amount',
            'display_name' => 'Total Amount',
            'semantic_type' => 'amount',
            'is_dimension' => false,
            'is_metric' => true,
            'default_aggregate' => 'avg',
            'sort_order' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.field_alias', 'total_amount')
            ->assertJsonPath('data.display_name', 'Total Amount')
            ->assertJsonPath('data.default_aggregate', 'avg');

        $this->getJson("/api/datasets/{$dataset->id}/fields")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonFragment([
                'field_alias' => 'total_amount',
                'display_name' => 'Total Amount',
            ]);
    }

    public function test_dataset_preview_only_queries_bound_table_and_saved_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $connection = $this->fakeMetadataDriver();

        $dataset = $this->createDatasetWithSyncedFields();

        $connection
            ->shouldReceive('select')
            ->once()
            ->with('select `id`, `amount` from `orders` limit 2')
            ->andReturn([
                (object) ['id' => 1, 'amount' => '12.50'],
                (object) ['id' => 2, 'amount' => '7.00'],
            ]);

        $this->postJson("/api/datasets/{$dataset->id}/preview", [
            'fields' => ['id', 'amount'],
            'limit' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.columns', ['id', 'amount'])
            ->assertJsonPath('data.rows.0.id', 1)
            ->assertJsonPath('data.rows.1.amount', '7.00')
            ->assertJsonPath('data.limit', 2);
    }

    public function test_dataset_creation_rejects_tables_outside_selected_data_source(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->fakeMetadataDriver();

        $dataSource = $this->createDataSource();

        $this->postJson('/api/datasets', [
            'name' => 'Invalid Dataset',
            'data_source_id' => $dataSource->id,
            'main_table' => 'payments',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    public function test_dataset_preview_rejects_fields_outside_current_dataset(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->fakeMetadataDriver();

        $dataset = $this->createDatasetWithSyncedFields();

        $this->postJson("/api/datasets/{$dataset->id}/preview", [
            'fields' => ['id', 'unsafe_field'],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    public function test_dataset_endpoints_require_authentication(): void
    {
        $this->getJson('/api/datasets')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
    }

    private function createDatasetWithSyncedFields(): Dataset
    {
        $dataSource = $this->createDataSource();

        $datasetId = $this->postJson('/api/datasets', [
            'name' => 'Orders Dataset',
            'data_source_id' => $dataSource->id,
            'main_table' => 'orders',
        ])
            ->assertCreated()
            ->json('data.id');

        return Dataset::query()->with('fields')->findOrFail($datasetId);
    }

    private function createDataSource(): DataSource
    {
        return DataSource::query()->create([
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
    }

    private function fakeMetadataDriver(): ConnectionInterface
    {
        $connection = Mockery::mock(ConnectionInterface::class);

        $factory = Mockery::mock(DataSourceConnectionFactory::class);
        $factory->shouldReceive('make')->andReturn($connection);
        $factory->shouldReceive('disconnect')->zeroOrMoreTimes();
        $this->app->instance(DataSourceConnectionFactory::class, $factory);

        $driver = new class implements DatabaseDriverInterface
        {
            public function test(ConnectionInterface $connection): void
            {
                //
            }

            public function tables(ConnectionInterface $connection, DataSource $dataSource): array
            {
                return [
                    [
                        'table_name' => 'orders',
                        'table_comment' => 'Orders',
                        'table_type' => 'BASE TABLE',
                        'row_count_estimate' => 10,
                    ],
                ];
            }

            public function fields(ConnectionInterface $connection, DataSource $dataSource, string $tableName): array
            {
                return [
                    [
                        'table_name' => $tableName,
                        'field_name' => 'id',
                        'field_comment' => 'ID',
                        'data_type' => 'bigint unsigned',
                        'normalized_type' => 'integer',
                        'is_nullable' => false,
                        'is_primary_key' => true,
                        'default_value' => null,
                        'ordinal_position' => 1,
                    ],
                    [
                        'table_name' => $tableName,
                        'field_name' => 'amount',
                        'field_comment' => 'Amount',
                        'data_type' => 'decimal(12,2)',
                        'normalized_type' => 'decimal',
                        'is_nullable' => false,
                        'is_primary_key' => false,
                        'default_value' => '0.00',
                        'ordinal_position' => 2,
                    ],
                    [
                        'table_name' => $tableName,
                        'field_name' => 'ordered_at',
                        'field_comment' => 'Ordered At',
                        'data_type' => 'datetime',
                        'normalized_type' => 'datetime',
                        'is_nullable' => false,
                        'is_primary_key' => false,
                        'default_value' => null,
                        'ordinal_position' => 3,
                    ],
                ];
            }
        };

        $manager = Mockery::mock(DataSourceDriverManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(DataSourceDriverManager::class, $manager);

        return $connection;
    }
}
