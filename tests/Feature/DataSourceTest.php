<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\DataSource\Drivers\DatabaseDriverInterface;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourceDriverManager;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DataSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_mysql_data_source_without_exposing_password(): void
    {
        $actor = User::factory()->create();
        Sanctum::actingAs($actor);

        $dataSourceId = $this->postJson('/api/data-sources', [
            'name' => 'Sales MySQL',
            'type' => 'mysql',
            'host' => 'mysql.example.test',
            'port' => 3306,
            'database_name' => 'sales',
            'username' => 'reporter',
            'password' => 'secret-password',
            'options_json' => [
                'timeout' => 3,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('code', 0)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.password_encrypted')
            ->assertJsonPath('data.created_by', $actor->id)
            ->json('data.id');

        $dataSource = DataSource::query()->findOrFail($dataSourceId);

        $this->assertNotSame('secret-password', $dataSource->password_encrypted);
        $this->assertSame('secret-password', app(DataSourcePasswordEncryptor::class)->decrypt($dataSource->password_encrypted));

        $this->getJson('/api/data-sources?page_size=10')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonMissingPath('data.items.0.password_encrypted');

        $this->putJson("/api/data-sources/{$dataSourceId}", [
            'status' => 'disabled',
            'host' => 'mysql.internal',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.host', 'mysql.internal')
            ->assertJsonMissingPath('data.password_encrypted');

        $this->assertSame('secret-password', app(DataSourcePasswordEncryptor::class)->decrypt($dataSource->refresh()->password_encrypted));

        $this->deleteJson("/api/data-sources/{$dataSourceId}")
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSoftDeleted('data_sources', [
            'id' => $dataSourceId,
        ]);
    }

    public function test_metadata_endpoints_can_test_read_and_sync_tables_and_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->fakeMetadataDriver();

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

        $this->postJson("/api/data-sources/{$dataSource->id}/test")
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertTrue((bool) $dataSource->refresh()->last_test_result['success']);

        $this->getJson("/api/data-sources/{$dataSource->id}/tables")
            ->assertOk()
            ->assertJsonPath('data.0.table_name', 'orders');

        $this->getJson("/api/data-sources/{$dataSource->id}/tables/orders/fields")
            ->assertOk()
            ->assertJsonPath('data.0.field_name', 'id')
            ->assertJsonPath('data.1.normalized_type', 'decimal');

        $this->postJson("/api/data-sources/{$dataSource->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.tables', 1)
            ->assertJsonPath('data.fields', 2);

        $table = DataSourceTable::query()->where('data_source_id', $dataSource->id)->firstOrFail();

        $this->assertSame('orders', $table->table_name);
        $this->assertSame(2, DataSourceField::query()->where('table_id', $table->id)->count());
        $this->assertDatabaseHas('data_source_fields', [
            'data_source_id' => $dataSource->id,
            'table_name' => 'orders',
            'field_name' => 'amount',
            'normalized_type' => 'decimal',
        ]);

        $this->deleteJson("/api/data-sources/{$dataSource->id}")
            ->assertOk();

        $this->assertDatabaseMissing('data_source_tables', [
            'data_source_id' => $dataSource->id,
            'table_name' => 'orders',
        ]);
        $this->assertDatabaseMissing('data_source_fields', [
            'data_source_id' => $dataSource->id,
            'table_name' => 'orders',
        ]);
    }

    public function test_table_field_endpoint_rejects_unsafe_table_names(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->fakeMetadataDriver();

        $dataSource = DataSource::query()->create([
            'name' => 'Analytics MySQL',
            'type' => 'mysql',
            'host' => 'mysql',
            'port' => 3306,
            'database_name' => 'analytics',
            'username' => 'reporter',
            'charset' => 'utf8mb4',
            'timezone' => '+00:00',
            'status' => 'active',
        ]);

        $this->getJson("/api/data-sources/{$dataSource->id}/tables/bad-name/fields")
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    public function test_data_source_endpoints_require_authentication(): void
    {
        $this->getJson('/api/data-sources')
            ->assertUnauthorized()
            ->assertJsonPath('code', 40100);
    }

    private function fakeMetadataDriver(): void
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

            public function databases(ConnectionInterface $connection, DataSource $dataSource): array
            {
                return [
                    [
                        'database_name' => $dataSource->database_name,
                        'name' => $dataSource->database_name,
                    ],
                ];
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

            public function views(ConnectionInterface $connection, DataSource $dataSource): array
            {
                return [];
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
                ];
            }

            public function preview(ConnectionInterface $connection, DataSource $dataSource, string $tableName, int $limit = 100): array
            {
                return [
                    'columns' => ['id', 'amount'],
                    'rows' => [],
                    'limit' => $limit,
                ];
            }

            public function explain(ConnectionInterface $connection, DataSource $dataSource, string $sql, array $bindings = []): array
            {
                return [
                    [
                        'id' => 1,
                        'select_type' => 'SIMPLE',
                    ],
                ];
            }

            public function materializedViews(ConnectionInterface $connection, DataSource $dataSource): array
            {
                return [];
            }

            public function materializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): ?array
            {
                return null;
            }

            public function refreshMaterializedView(ConnectionInterface $connection, DataSource $dataSource, string $name): array
            {
                return [
                    'refreshed' => false,
                    'message' => 'Unsupported.',
                ];
            }

            public function dialect(): string
            {
                return 'mysql';
            }
        };

        $manager = Mockery::mock(DataSourceDriverManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(DataSourceDriverManager::class, $manager);
    }
}
