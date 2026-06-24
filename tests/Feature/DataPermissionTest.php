<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Services\DataSourceConnectionFactory;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Permission\Models\Role;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DataPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_resource_permission_controls_dataset_query_access(): void
    {
        $admin = User::factory()->create();
        $allowedUser = User::factory()->create();
        $deniedUser = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Regional Manager',
            'code' => 'regional_manager',
        ]);
        $allowedUser->roles()->attach($role->id);
        $dataset = $this->createDataset();

        Sanctum::actingAs($admin);

        $this->postJson('/api/resource-permissions', [
            'resource_type' => 'dataset',
            'resource_id' => $dataset->id,
            'subject_type' => 'role',
            'subject_id' => $role->id,
            'permission_type' => 'view',
        ])
            ->assertCreated()
            ->assertJsonPath('data.resource_type', 'dataset')
            ->assertJsonPath('data.subject_type', 'role');

        Sanctum::actingAs($deniedUser);

        $this->postJson('/api/query/execute', $this->queryPayload($dataset))
            ->assertForbidden()
            ->assertJsonPath('code', 40300);

        Sanctum::actingAs($allowedUser);
        $connection = $this->fakeConnection();
        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` group by `province` limit 10 offset 0';

        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1200.00'],
            ]);

        $this->postJson('/api/query/execute', $this->queryPayload($dataset))
            ->assertOk()
            ->assertJsonPath('data.rows.0.province', 'GD');
    }

    public function test_row_level_permission_rule_is_appended_to_query_sql(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Guangdong Sales',
            'code' => 'gd_sales',
        ]);
        $user->roles()->attach($role->id);
        Sanctum::actingAs($user);
        $dataset = $this->createDataset();

        $this->postJson('/api/data-permission-rules', [
            'dataset_id' => $dataset->id,
            'subject_type' => 'role',
            'subject_id' => $role->id,
            'field_name' => 'province',
            'operator' => '=',
            'value_json' => 'GD',
        ])
            ->assertCreated()
            ->assertJsonPath('data.value_json', 'GD');

        $connection = $this->fakeConnection();
        $expectedSql = 'select `province` as `province`, sum(`amount`) as `amount_sum` from `orders` where `year` = ? and `province` = ? group by `province` limit 10 offset 0';

        $connection
            ->shouldReceive('select')
            ->once()
            ->with($expectedSql, [2026, 'GD'])
            ->andReturn([
                (object) ['province' => 'GD', 'amount_sum' => '1200.00'],
            ]);

        $this->postJson('/api/query/execute', [
            ...$this->queryPayload($dataset),
            'filters' => [
                ['field' => 'year', 'operator' => '=', 'value' => 2026],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.rows.0.amount_sum', '1200.00');
    }

    public function test_column_permission_can_hide_field_from_query(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset();

        $this->postJson('/api/column-permission-rules', [
            'dataset_id' => $dataset->id,
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'field_name' => 'amount',
            'permission_type' => 'hidden',
        ])
            ->assertCreated()
            ->assertJsonPath('data.field_name', 'amount');

        $this->postJson('/api/query/execute', $this->queryPayload($dataset))
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    public function test_permission_rules_reject_fields_outside_dataset(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $dataset = $this->createDataset();

        $this->postJson('/api/data-permission-rules', [
            'dataset_id' => $dataset->id,
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'field_name' => 'unsafe_field',
            'operator' => '=',
            'value_json' => 'GD',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);

        $this->postJson('/api/column-permission-rules', [
            'dataset_id' => $dataset->id,
            'subject_type' => 'user',
            'subject_id' => $user->id,
            'field_name' => 'unsafe_field',
            'permission_type' => 'hidden',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 40001);
    }

    /**
     * @return array<string, mixed>
     */
    private function queryPayload(Dataset $dataset): array
    {
        return [
            'dataset_id' => $dataset->id,
            'dimensions' => [
                ['field' => 'province'],
            ],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
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
