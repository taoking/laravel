<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Acceleration\Models\AccelerationAggregateDefinition;
use App\Modules\Acceleration\Models\AccelerationProfile;
use App\Modules\Acceleration\Services\AccelerationProfileService;
use App\Modules\Acceleration\Services\AccelerationSchemaService;
use App\Modules\Acceleration\Services\AccelerationSyncService;
use App\Modules\Acceleration\Services\AggregateBuildService;
use App\Modules\Acceleration\Services\AggregateDefinitionService;
use App\Modules\Acceleration\Services\ClickHouseClient;
use App\Modules\Chart\Models\Chart;
use App\Modules\Dashboard\Models\Dashboard;
use App\Modules\Dashboard\Models\DashboardFilter;
use App\Modules\Dashboard\Models\DashboardWidget;
use App\Modules\DataPermission\Models\ColumnPermissionRule;
use App\Modules\DataPermission\Models\DataPermissionRule;
use App\Modules\Dataset\Models\Dataset;
use App\Modules\Dataset\Models\DatasetField;
use App\Modules\Dataset\Models\DatasetTable;
use App\Modules\DataSource\Models\DataSource;
use App\Modules\DataSource\Models\DataSourceField;
use App\Modules\DataSource\Models\DataSourceTable;
use App\Modules\DataSource\Services\DataSourcePasswordEncryptor;
use App\Modules\Permission\Models\Permission;
use App\Modules\Permission\Models\Role;
use App\Modules\Semantic\Models\Dimension;
use App\Modules\Semantic\Models\Metric;
use App\Modules\Semantic\Models\MetricCategory;
use App\Modules\Semantic\Models\MetricDependency;
use App\Modules\Semantic\Models\MetricVersion;
use App\Modules\Semantic\Services\MetricFormulaParser;
use App\Modules\User\Models\Department;
use App\Modules\User\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DemoBiSeeder extends Seeder
{
    private const SALES_TABLE = 'sales_orders';

    private const DATA_SOURCE_NAME = 'Demo MySQL Sales';

    private const DATASET_NAME = '销售订单数据集';

    private const DASHBOARD_NAME = '电商销售分析看板';

    private const DETAIL_ACCELERATION_NAME = '销售订单明细 ClickHouse 加速';

    private const DETAIL_ACCELERATION_TABLE = 'demo_sales_orders_detail';

    private const AGGREGATE_ACCELERATION_NAME = '销售订单月度省份聚合加速';

    private const AGGREGATE_ACCELERATION_TABLE = 'demo_sales_orders_monthly_province_agg';

    /**
     * @var list<string>
     */
    private const CHART_NAMES = [
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
    ];

    /**
     * @var list<string>
     */
    private const METRIC_CODES = [
        'sales_amount',
        'order_count',
        'total_quantity',
        'refund_amount_sum',
        'cost_amount_sum',
        'profit_amount_sum',
        'avg_order_amount',
        'profit_rate',
        'refund_rate',
    ];

    public function run(): void
    {
        $this->seed();
    }

    /**
     * @return array<string, mixed>
     */
    public function seed(int $orders = 10000, bool $fresh = false, bool $skipLargeData = false): array
    {
        $targetOrders = $skipLargeData ? min(max($orders, 1), 500) : min(max($orders, 5000), 20000);

        if ($fresh) {
            $this->freshDemo();
        }

        $this->ensureSalesOrdersTable();
        $users = $this->seedUsersAndRoles();
        $createdOrders = $this->seedSalesOrders($targetOrders);
        $dataSource = $this->seedDataSource($users['admin']);
        $this->seedDataSourceMetadata($dataSource, $targetOrders);
        $dataset = $this->seedDataset($dataSource, $users['admin']);
        $metrics = $this->seedMetrics($dataset, $users['admin']);
        $dimensions = $this->seedDimensions($dataset, $users['admin']);
        $charts = $this->seedCharts($dataset, $users['admin']);
        $dashboard = $this->seedDashboard($charts, $users['admin']);
        $this->seedDataPermissions($dataset, $users['viewer']);
        $acceleration = $this->seedClickHouseAcceleration($dataset, $users['admin']);

        return [
            'users' => count($users),
            'orders_target' => $targetOrders,
            'orders_created' => $createdOrders,
            'orders_total' => DB::table(self::SALES_TABLE)->count(),
            'data_source' => $dataSource->name,
            'dataset' => $dataset->name,
            'metrics' => count($metrics),
            'dimensions' => count($dimensions),
            'charts' => count($charts),
            'dashboard' => $dashboard->name,
            'quality_rules' => 0,
            'clickhouse_acceleration' => $acceleration['built'],
            'clickhouse_acceleration_status' => $acceleration['status'],
            'clickhouse_detail_rows' => $acceleration['detail_rows'],
            'clickhouse_aggregate_rows' => $acceleration['aggregate_rows'],
            'clickhouse_acceleration_error' => $acceleration['error'],
        ];
    }

    private function freshDemo(): void
    {
        Dashboard::withTrashed()
            ->where('name', self::DASHBOARD_NAME)
            ->get()
            ->each(fn (Dashboard $dashboard): ?bool => $dashboard->forceDelete());

        Chart::withTrashed()
            ->whereIn('name', self::CHART_NAMES)
            ->get()
            ->each(fn (Chart $chart): ?bool => $chart->forceDelete());

        Metric::query()->whereIn('code', self::METRIC_CODES)->delete();

        Dataset::withTrashed()
            ->where('name', self::DATASET_NAME)
            ->get()
            ->each(fn (Dataset $dataset): ?bool => $dataset->forceDelete());

        DataSource::withTrashed()
            ->where('name', self::DATA_SOURCE_NAME)
            ->get()
            ->each(fn (DataSource $dataSource): ?bool => $dataSource->forceDelete());

        if (Schema::hasTable(self::SALES_TABLE)) {
            Schema::drop(self::SALES_TABLE);
        }
    }

    private function ensureSalesOrdersTable(): void
    {
        if (Schema::hasTable(self::SALES_TABLE)) {
            return;
        }

        Schema::create(self::SALES_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('order_no')->index();
            $table->date('order_date')->index();
            $table->string('province', 64)->index();
            $table->string('city', 64)->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('department_name', 100)->index();
            $table->unsignedBigInteger('sales_user_id')->nullable()->index();
            $table->string('sales_user_name', 100)->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_type', 40)->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_name', 120);
            $table->string('product_category', 60)->index();
            $table->string('channel', 60)->index();
            $table->string('payment_method', 60)->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->decimal('cost_amount', 12, 2)->default(0);
            $table->decimal('profit_amount', 12, 2)->default(0);
            $table->string('order_status', 40)->index();
            $table->timestamps();
        });
    }

    /**
     * @return array{admin: User, analyst: User, viewer: User}
     */
    private function seedUsersAndRoles(): array
    {
        $organization = Organization::query()->updateOrCreate(
            ['code' => 'demo'],
            [
                'name' => 'Demo Organization',
                'status' => 'active',
            ],
        );
        $department = Department::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'code' => 'sales',
            ],
            [
                'name' => 'Sales Department',
                'status' => 'active',
                'sort_order' => 10,
            ],
        );

        $permissions = $this->seedPermissions();
        $adminRole = $this->role('admin', 'Administrator', $permissions);
        $analystRole = $this->role('analyst', 'Analyst', $permissions->where('code', '!=', 'users.delete'));
        $viewerRole = $this->role('viewer', 'Viewer', $permissions->whereIn('code', [
            'charts.view',
            'dashboards.view',
            'datasets.view',
        ]));

        $admin = $this->user('admin@example.com', 'Administrator', $organization->id, $department->id, $adminRole);
        $analyst = $this->user('analyst@example.com', 'Demo Analyst', $organization->id, $department->id, $analystRole);
        $viewer = $this->user('viewer@example.com', 'Demo Viewer', $organization->id, $department->id, $viewerRole);

        return [
            'admin' => $admin,
            'analyst' => $analyst,
            'viewer' => $viewer,
        ];
    }

    /**
     * @return Collection<int, Permission>
     */
    private function seedPermissions()
    {
        return collect([
            ['name' => 'View datasets', 'code' => 'datasets.view', 'group' => 'datasets'],
            ['name' => 'Manage datasets', 'code' => 'datasets.manage', 'group' => 'datasets'],
            ['name' => 'View charts', 'code' => 'charts.view', 'group' => 'charts'],
            ['name' => 'Manage charts', 'code' => 'charts.manage', 'group' => 'charts'],
            ['name' => 'View dashboards', 'code' => 'dashboards.view', 'group' => 'dashboards'],
            ['name' => 'Manage dashboards', 'code' => 'dashboards.manage', 'group' => 'dashboards'],
            ['name' => 'Manage data sources', 'code' => 'data_sources.manage', 'group' => 'data_sources'],
            ['name' => 'Manage semantic layer', 'code' => 'semantic.manage', 'group' => 'semantic'],
            ['name' => 'Manage data permissions', 'code' => 'data_permissions.manage', 'group' => 'data_permissions'],
            ['name' => 'Manage users', 'code' => 'users.manage', 'group' => 'users'],
            ['name' => 'Delete users', 'code' => 'users.delete', 'group' => 'users'],
            ['name' => 'Manage acceleration', 'code' => 'acceleration.manage', 'group' => 'acceleration'],
        ])->map(fn (array $definition): Permission => Permission::query()->updateOrCreate(
            ['code' => $definition['code']],
            [
                'name' => $definition['name'],
                'guard_name' => 'sanctum',
                'group' => $definition['group'],
            ],
        ));
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     */
    private function role(string $code, string $name, $permissions): Role
    {
        $role = Role::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'guard_name' => 'sanctum',
                'description' => 'Demo '.$name.' role.',
                'is_system' => true,
            ],
        );
        $role->permissions()->sync($permissions->pluck('id')->all());

        return $role;
    }

    private function user(string $email, string $name, int $organizationId, int $departmentId, Role $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'organization_id' => $organizationId,
                'department_id' => $departmentId,
                'name' => $name,
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    private function seedSalesOrders(int $targetOrders): int
    {
        $existing = DB::table(self::SALES_TABLE)->count();

        if ($existing >= $targetOrders) {
            return 0;
        }

        mt_srand(20260624 + $existing);

        $rowsToCreate = $targetOrders - $existing;
        $created = 0;
        $batch = [];
        $now = now();
        $provinces = [
            '广东' => ['广州', '深圳', '佛山', '东莞', '珠海'],
            '湖南' => ['长沙', '株洲', '湘潭', '衡阳'],
            '湖北' => ['武汉', '宜昌', '襄阳', '荆州'],
            '广西' => ['南宁', '桂林', '柳州', '北海'],
            '浙江' => ['杭州', '宁波', '温州', '金华'],
            '江苏' => ['南京', '苏州', '无锡', '常州'],
            '四川' => ['成都', '绵阳', '德阳', '宜宾'],
            '北京' => ['北京'],
            '上海' => ['上海'],
        ];
        $departments = [
            101 => '华南销售部',
            102 => '华中销售部',
            103 => '华东销售部',
            104 => '西南销售部',
            105 => '重点客户部',
        ];
        $salesUsers = [
            1001 => '陈明',
            1002 => '李娜',
            1003 => '王强',
            1004 => '刘洋',
            1005 => '张敏',
            1006 => '赵磊',
        ];
        $products = [
            '手机' => ['旗舰手机 Pro', '青春版手机', '折叠屏手机'],
            '电脑' => ['轻薄笔记本', '游戏笔记本', '办公台式机'],
            '家电' => ['智能冰箱', '洗烘一体机', '空气净化器'],
            '服饰' => ['运动卫衣', '商务衬衫', '户外冲锋衣'],
            '食品' => ['坚果礼盒', '咖啡套装', '有机米'],
            '图书' => ['管理学精选', '少儿绘本', '技术书籍'],
            '户外' => ['露营帐篷', '登山背包', '便携炉具'],
            '数码配件' => ['无线耳机', '移动电源', '机械键盘'],
        ];
        $channels = ['官网', '小程序', '天猫', '京东', '抖音', '线下门店'];
        $paymentMethods = ['微信支付', '支付宝', '信用卡', '银行转账', '现金'];
        $customerTypes = ['新客户', '老客户', '会员客户', '企业客户'];

        for ($i = 1; $i <= $rowsToCreate; $i++) {
            $sequence = $existing + $i;
            $province = array_rand($provinces);
            $city = $provinces[$province][array_rand($provinces[$province])];
            $departmentId = array_rand($departments);
            $salesUserId = array_rand($salesUsers);
            $category = array_rand($products);
            $productName = $products[$category][array_rand($products[$category])];
            $quantity = mt_rand(1, 5);
            $basePrice = match ($category) {
                '手机' => mt_rand(1800, 7800),
                '电脑' => mt_rand(3500, 12000),
                '家电' => mt_rand(900, 8500),
                '服饰' => mt_rand(80, 900),
                '食品' => mt_rand(30, 500),
                '图书' => mt_rand(20, 180),
                '户外' => mt_rand(120, 2600),
                default => mt_rand(50, 1600),
            };
            $gross = $basePrice * $quantity;
            $discount = round($gross * mt_rand(0, 18) / 100, 2);
            $amount = round($gross - $discount + mt_rand(-50, 50), 2);
            $status = $this->weightedStatus();
            $refund = $status === 'refunded' ? round($amount * mt_rand(20, 100) / 100, 2) : 0.0;
            $cost = round(max($amount, 0) * mt_rand(52, 78) / 100, 2);
            $profit = round($amount - $refund - $cost, 2);
            $orderNo = 'SO-DEMO-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);

            if ($sequence % 887 === 0) {
                $orderNo = 'SO-DEMO-'.str_pad((string) max(1, $sequence - 1), 8, '0', STR_PAD_LEFT);
            }

            if ($sequence % 1231 === 0) {
                $amount = -1 * abs($amount);
                $profit = round($amount - $refund - $cost, 2);
            }

            $customerName = $sequence % 997 === 0 ? null : '客户'.str_pad((string) mt_rand(1, 1800), 4, '0', STR_PAD_LEFT);

            $batch[] = [
                'order_no' => $orderNo,
                'order_date' => CarbonImmutable::now()->subDays(mt_rand(0, 365))->toDateString(),
                'province' => $province,
                'city' => $city,
                'department_id' => $departmentId,
                'department_name' => $departments[$departmentId],
                'sales_user_id' => $salesUserId,
                'sales_user_name' => $salesUsers[$salesUserId],
                'customer_id' => mt_rand(1, 1800),
                'customer_name' => $customerName,
                'customer_type' => $customerTypes[array_rand($customerTypes)],
                'product_id' => ((int) sprintf('%u', crc32($category.$productName))) % 100000,
                'product_name' => $productName,
                'product_category' => $category,
                'channel' => $channels[array_rand($channels)],
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'amount' => $amount,
                'quantity' => $quantity,
                'discount_amount' => $discount,
                'refund_amount' => $refund,
                'cost_amount' => $cost,
                'profit_amount' => $profit,
                'order_status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                DB::table(self::SALES_TABLE)->insert($batch);
                $created += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table(self::SALES_TABLE)->insert($batch);
            $created += count($batch);
        }

        return $created;
    }

    private function weightedStatus(): string
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 82 => 'paid',
            $roll <= 90 => 'pending',
            $roll <= 96 => 'refunded',
            default => 'cancelled',
        };
    }

    private function seedDataSource(User $admin): DataSource
    {
        $connection = $this->mysqlConnectionConfig();

        return DataSource::query()->updateOrCreate(
            ['name' => self::DATA_SOURCE_NAME],
            [
                'type' => 'mysql',
                'host' => (string) ($connection['host'] ?? '127.0.0.1'),
                'port' => (int) ($connection['port'] ?? 3306),
                'database_name' => (string) ($connection['database'] ?? config('database.connections.mysql.database', 'bi_platform')),
                'username' => (string) ($connection['username'] ?? 'root'),
                'password_encrypted' => app(DataSourcePasswordEncryptor::class)->encrypt((string) ($connection['password'] ?? '')),
                'charset' => (string) ($connection['charset'] ?? 'utf8mb4'),
                'timezone' => '+00:00',
                'options_json' => [
                    'timeout' => 5,
                    'demo' => true,
                    'source' => 'current_application_database',
                ],
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mysqlConnectionConfig(): array
    {
        $default = config('database.default');
        $defaultConnection = is_string($default) ? config("database.connections.{$default}", []) : [];

        if (($defaultConnection['driver'] ?? null) === 'mysql') {
            return $defaultConnection;
        }

        return config('database.connections.mysql', []);
    }

    private function seedDataSourceMetadata(DataSource $dataSource, int $rowEstimate): void
    {
        $table = DataSourceTable::query()->updateOrCreate(
            [
                'data_source_id' => $dataSource->id,
                'table_name' => self::SALES_TABLE,
            ],
            [
                'table_comment' => '电商销售订单 Demo 明细表',
                'table_type' => 'BASE TABLE',
                'row_count_estimate' => $rowEstimate,
                'synced_at' => now(),
            ],
        );

        foreach ($this->fieldDefinitions() as $index => $field) {
            DataSourceField::query()->updateOrCreate(
                [
                    'data_source_id' => $dataSource->id,
                    'table_name' => self::SALES_TABLE,
                    'field_name' => $field['field_name'],
                ],
                [
                    'table_id' => $table->id,
                    'field_comment' => $field['display_name'],
                    'data_type' => $field['data_type'],
                    'normalized_type' => $field['normalized_type'],
                    'is_nullable' => in_array($field['field_name'], ['customer_name'], true),
                    'is_primary_key' => $field['field_name'] === 'id',
                    'default_value' => null,
                    'ordinal_position' => $index + 1,
                ],
            );
        }
    }

    private function seedDataset(DataSource $dataSource, User $admin): Dataset
    {
        $dataset = Dataset::query()->updateOrCreate(
            ['name' => self::DATASET_NAME],
            [
                'description' => '基于 sales_orders 的电商销售分析 Demo 数据集。',
                'data_source_id' => $dataSource->id,
                'dataset_type' => 'single_table',
                'main_table' => self::SALES_TABLE,
                'config_json' => [
                    'demo' => true,
                    'topic' => '电商销售分析',
                ],
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        DatasetTable::query()->updateOrCreate(
            [
                'dataset_id' => $dataset->id,
                'table_name' => self::SALES_TABLE,
            ],
            [
                'data_source_id' => $dataSource->id,
                'alias' => 'orders',
                'join_type' => null,
                'join_condition' => null,
                'sort_order' => 0,
            ],
        );

        foreach ($this->fieldDefinitions() as $index => $field) {
            DatasetField::query()->updateOrCreate(
                [
                    'dataset_id' => $dataset->id,
                    'table_name' => self::SALES_TABLE,
                    'field_name' => $field['field_name'],
                ],
                [
                    'field_alias' => $field['field_name'],
                    'display_name' => $field['display_name'],
                    'source_type' => 'physical',
                    'normalized_type' => $field['normalized_type'],
                    'semantic_type' => $field['semantic_type'],
                    'is_dimension' => $field['is_dimension'],
                    'is_metric' => $field['is_metric'],
                    'is_visible' => true,
                    'is_filterable' => $field['is_filterable'],
                    'default_aggregate' => $field['default_aggregate'],
                    'expression' => null,
                    'sort_order' => $index + 1,
                ],
            );
        }

        return $dataset->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fieldDefinitions(): array
    {
        return [
            ['field_name' => 'id', 'display_name' => 'ID', 'data_type' => 'bigint', 'normalized_type' => 'integer', 'semantic_type' => 'id', 'is_dimension' => false, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'count'],
            ['field_name' => 'order_no', 'display_name' => '订单号', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'count'],
            ['field_name' => 'order_date', 'display_name' => '订单日期', 'data_type' => 'date', 'normalized_type' => 'date', 'semantic_type' => 'time', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'province', 'display_name' => '省份', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'province', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'city', 'display_name' => '城市', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'city', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'department_id', 'display_name' => '部门 ID', 'data_type' => 'bigint', 'normalized_type' => 'integer', 'semantic_type' => 'organization', 'is_dimension' => false, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'department_name', 'display_name' => '部门', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'organization', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'sales_user_id', 'display_name' => '销售人员 ID', 'data_type' => 'bigint', 'normalized_type' => 'integer', 'semantic_type' => 'user', 'is_dimension' => false, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'sales_user_name', 'display_name' => '销售人员', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'user', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'customer_id', 'display_name' => '客户 ID', 'data_type' => 'bigint', 'normalized_type' => 'integer', 'semantic_type' => 'normal', 'is_dimension' => false, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'customer_name', 'display_name' => '客户名称', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'customer_type', 'display_name' => '客户类型', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'product_id', 'display_name' => '商品 ID', 'data_type' => 'bigint', 'normalized_type' => 'integer', 'semantic_type' => 'normal', 'is_dimension' => false, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'product_name', 'display_name' => '商品名称', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'product_category', 'display_name' => '产品分类', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'channel', 'display_name' => '销售渠道', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'payment_method', 'display_name' => '支付方式', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'amount', 'display_name' => '销售额', 'data_type' => 'decimal', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'sum'],
            ['field_name' => 'quantity', 'display_name' => '销量', 'data_type' => 'int', 'normalized_type' => 'integer', 'semantic_type' => 'number', 'is_dimension' => false, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'sum'],
            ['field_name' => 'discount_amount', 'display_name' => '优惠金额', 'data_type' => 'decimal', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'sum'],
            ['field_name' => 'refund_amount', 'display_name' => '退款金额', 'data_type' => 'decimal', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'sum'],
            ['field_name' => 'cost_amount', 'display_name' => '成本金额', 'data_type' => 'decimal', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'sum'],
            ['field_name' => 'profit_amount', 'display_name' => '利润金额', 'data_type' => 'decimal', 'normalized_type' => 'decimal', 'semantic_type' => 'amount', 'is_dimension' => false, 'is_metric' => true, 'is_filterable' => true, 'default_aggregate' => 'sum'],
            ['field_name' => 'order_status', 'display_name' => '订单状态', 'data_type' => 'varchar', 'normalized_type' => 'string', 'semantic_type' => 'normal', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'created_at', 'display_name' => '创建时间', 'data_type' => 'datetime', 'normalized_type' => 'datetime', 'semantic_type' => 'time', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
            ['field_name' => 'updated_at', 'display_name' => '更新时间', 'data_type' => 'datetime', 'normalized_type' => 'datetime', 'semantic_type' => 'time', 'is_dimension' => true, 'is_metric' => false, 'is_filterable' => true, 'default_aggregate' => 'none'],
        ];
    }

    /**
     * @return list<Metric>
     */
    private function seedMetrics(Dataset $dataset, User $admin): array
    {
        $category = MetricCategory::query()->updateOrCreate(
            ['name' => '电商销售 Demo'],
            [
                'parent_id' => null,
                'sort_order' => 10,
                'description' => '电商销售分析 Demo 指标。',
            ],
        );
        $definitions = [
            ['name' => '销售额', 'code' => 'sales_amount', 'metric_type' => 'base', 'aggregate_function' => 'sum', 'source_field' => 'amount', 'formula' => null, 'unit' => '元', 'format_type' => 'currency'],
            ['name' => '订单数', 'code' => 'order_count', 'metric_type' => 'base', 'aggregate_function' => 'count', 'source_field' => 'order_no', 'formula' => null, 'unit' => '单', 'format_type' => 'number'],
            ['name' => '销量', 'code' => 'total_quantity', 'metric_type' => 'base', 'aggregate_function' => 'sum', 'source_field' => 'quantity', 'formula' => null, 'unit' => '件', 'format_type' => 'number'],
            ['name' => '退款金额', 'code' => 'refund_amount_sum', 'metric_type' => 'base', 'aggregate_function' => 'sum', 'source_field' => 'refund_amount', 'formula' => null, 'unit' => '元', 'format_type' => 'currency'],
            ['name' => '成本', 'code' => 'cost_amount_sum', 'metric_type' => 'base', 'aggregate_function' => 'sum', 'source_field' => 'cost_amount', 'formula' => null, 'unit' => '元', 'format_type' => 'currency'],
            ['name' => '利润', 'code' => 'profit_amount_sum', 'metric_type' => 'base', 'aggregate_function' => 'sum', 'source_field' => 'profit_amount', 'formula' => null, 'unit' => '元', 'format_type' => 'currency'],
            ['name' => '客单价', 'code' => 'avg_order_amount', 'metric_type' => 'compound', 'aggregate_function' => 'expression', 'source_field' => null, 'formula' => 'sales_amount / order_count', 'unit' => '元', 'format_type' => 'currency'],
            ['name' => '利润率', 'code' => 'profit_rate', 'metric_type' => 'compound', 'aggregate_function' => 'expression', 'source_field' => null, 'formula' => 'profit_amount_sum / sales_amount', 'unit' => '%', 'format_type' => 'percent'],
            ['name' => '退款率', 'code' => 'refund_rate', 'metric_type' => 'compound', 'aggregate_function' => 'expression', 'source_field' => null, 'formula' => 'refund_amount_sum / sales_amount', 'unit' => '%', 'format_type' => 'percent'],
        ];
        $metrics = [];

        foreach ($definitions as $definition) {
            $metric = Metric::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'category_id' => $category->id,
                    'dataset_id' => $dataset->id,
                    'name' => $definition['name'],
                    'description' => 'Demo metric: '.$definition['name'],
                    'metric_type' => $definition['metric_type'],
                    'aggregate_function' => $definition['aggregate_function'],
                    'source_field' => $definition['source_field'],
                    'formula' => $definition['formula'],
                    'unit' => $definition['unit'],
                    'precision' => 2,
                    'format_type' => $definition['format_type'],
                    'status' => 'active',
                    'version' => 1,
                    'owner_id' => $admin->id,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
            $this->syncMetricSnapshot($metric, $admin);
            $metrics[$metric->code] = $metric->refresh();
        }

        foreach ($metrics as $metric) {
            $this->syncMetricDependencies($metric);
        }

        return array_values($metrics);
    }

    private function syncMetricSnapshot(Metric $metric, User $admin): void
    {
        MetricVersion::query()->updateOrCreate(
            [
                'metric_id' => $metric->id,
                'version' => $metric->version,
            ],
            [
                'name' => $metric->name,
                'description' => $metric->description,
                'metric_type' => $metric->metric_type,
                'aggregate_function' => $metric->aggregate_function,
                'source_field' => $metric->source_field,
                'formula' => $metric->formula,
                'unit' => $metric->unit,
                'precision' => $metric->precision,
                'format_type' => $metric->format_type,
                'status' => $metric->status,
                'change_summary' => 'Demo metric definition.',
                'created_by' => $admin->id,
                'created_at' => now(),
            ],
        );
    }

    private function syncMetricDependencies(Metric $metric): void
    {
        MetricDependency::query()->where('metric_id', $metric->id)->delete();

        if ($metric->metric_type === 'base' && $metric->source_field !== null) {
            MetricDependency::query()->create([
                'metric_id' => $metric->id,
                'depends_on_metric_id' => null,
                'depends_on_field_name' => $metric->source_field,
                'dependency_type' => 'field',
            ]);

            return;
        }

        foreach (app(MetricFormulaParser::class)->dependencies((string) $metric->formula) as $code) {
            $dependency = Metric::query()->where('dataset_id', $metric->dataset_id)->where('code', $code)->first();

            if ($dependency instanceof Metric) {
                MetricDependency::query()->create([
                    'metric_id' => $metric->id,
                    'depends_on_metric_id' => $dependency->id,
                    'depends_on_field_name' => null,
                    'dependency_type' => 'metric',
                ]);
            }
        }
    }

    /**
     * @return list<Dimension>
     */
    private function seedDimensions(Dataset $dataset, User $admin): array
    {
        $definitions = [
            ['name' => '订单日期', 'code' => 'order_date', 'field_name' => 'order_date', 'dimension_type' => 'date', 'time_grain_options_json' => ['year', 'quarter', 'month', 'week', 'day']],
            ['name' => '省份', 'code' => 'province', 'field_name' => 'province', 'dimension_type' => 'region', 'time_grain_options_json' => null],
            ['name' => '城市', 'code' => 'city', 'field_name' => 'city', 'dimension_type' => 'region', 'time_grain_options_json' => null],
            ['name' => '部门', 'code' => 'department_name', 'field_name' => 'department_name', 'dimension_type' => 'organization', 'time_grain_options_json' => null],
            ['name' => '销售人员', 'code' => 'sales_user_name', 'field_name' => 'sales_user_name', 'dimension_type' => 'user', 'time_grain_options_json' => null],
            ['name' => '客户类型', 'code' => 'customer_type', 'field_name' => 'customer_type', 'dimension_type' => 'enum', 'time_grain_options_json' => null],
            ['name' => '产品分类', 'code' => 'product_category', 'field_name' => 'product_category', 'dimension_type' => 'enum', 'time_grain_options_json' => null],
            ['name' => '销售渠道', 'code' => 'channel', 'field_name' => 'channel', 'dimension_type' => 'enum', 'time_grain_options_json' => null],
            ['name' => '支付方式', 'code' => 'payment_method', 'field_name' => 'payment_method', 'dimension_type' => 'enum', 'time_grain_options_json' => null],
            ['name' => '订单状态', 'code' => 'order_status', 'field_name' => 'order_status', 'dimension_type' => 'enum', 'time_grain_options_json' => null],
        ];

        return collect($definitions)
            ->map(fn (array $definition): Dimension => Dimension::query()->updateOrCreate(
                [
                    'dataset_id' => $dataset->id,
                    'code' => $definition['code'],
                ],
                [
                    'name' => $definition['name'],
                    'field_name' => $definition['field_name'],
                    'dimension_type' => $definition['dimension_type'],
                    'time_grain_options_json' => $definition['time_grain_options_json'],
                    'description' => 'Demo dimension: '.$definition['name'],
                    'status' => 'active',
                    'created_by' => $admin->id,
                ],
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, Chart>
     */
    private function seedCharts(Dataset $dataset, User $admin): array
    {
        $since = CarbonImmutable::now()->subMonths(11)->startOfMonth()->toDateString();
        $definitions = [
            '销售额指标卡' => [
                'chart_type' => 'metric_card',
                'config_json' => [
                    'metrics' => [['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum']],
                    'limit' => 1,
                    'use_cache' => true,
                ],
            ],
            '订单数指标卡' => [
                'chart_type' => 'metric_card',
                'config_json' => [
                    'metrics' => [['field' => 'order_no', 'aggregate' => 'count', 'alias' => 'order_count']],
                    'limit' => 1,
                    'use_cache' => true,
                ],
            ],
            '利润率指标卡' => [
                'chart_type' => 'metric_card',
                'config_json' => [
                    'semantic_metrics' => [['metric_code' => 'profit_rate']],
                    'limit' => 1,
                    'use_cache' => true,
                ],
            ],
            '最近 12 个月销售趋势折线图' => [
                'chart_type' => 'line',
                'config_json' => [
                    'dimensions' => [['field' => 'order_date', 'time_granularity' => 'month', 'alias' => 'order_month']],
                    'metrics' => [['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum']],
                    'filters' => [['field' => 'order_date', 'operator' => '>=', 'value' => $since]],
                    'sorts' => [['field' => 'order_month', 'direction' => 'asc']],
                    'limit' => 12,
                    'use_cache' => true,
                ],
            ],
            '省份销售额柱状图' => [
                'chart_type' => 'bar',
                'config_json' => $this->rankConfig('province', 'amount_sum', 10),
            ],
            '产品分类销售额饼图' => [
                'chart_type' => 'pie',
                'config_json' => $this->rankConfig('product_category', 'amount_sum', 8),
            ],
            '销售渠道订单数柱状图' => [
                'chart_type' => 'bar',
                'config_json' => [
                    'dimensions' => [['field' => 'channel']],
                    'metrics' => [['field' => 'order_no', 'aggregate' => 'count', 'alias' => 'order_count']],
                    'sorts' => [['field' => 'order_count', 'direction' => 'desc']],
                    'limit' => 10,
                    'use_cache' => true,
                ],
            ],
            '客户类型销售额柱状图' => [
                'chart_type' => 'bar',
                'config_json' => $this->rankConfig('customer_type', 'amount_sum', 10),
            ],
            '城市销售额排行表格' => [
                'chart_type' => 'table',
                'config_json' => [
                    'dimensions' => [['field' => 'province'], ['field' => 'city']],
                    'metrics' => [['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum']],
                    'sorts' => [['field' => 'amount_sum', 'direction' => 'desc']],
                    'limit' => 20,
                    'use_cache' => true,
                ],
            ],
            '销售明细表格' => [
                'chart_type' => 'table',
                'config_json' => [
                    'dimensions' => [
                        ['field' => 'order_no'],
                        ['field' => 'order_date'],
                        ['field' => 'province'],
                        ['field' => 'city'],
                        ['field' => 'product_name'],
                        ['field' => 'channel'],
                        ['field' => 'order_status'],
                    ],
                    'metrics' => [['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum']],
                    'sorts' => [['field' => 'order_no', 'direction' => 'desc']],
                    'limit' => 50,
                    'use_cache' => true,
                ],
            ],
        ];
        $charts = [];

        foreach ($definitions as $name => $definition) {
            $charts[$name] = Chart::query()->updateOrCreate(
                ['name' => $name],
                [
                    'description' => '电商销售分析 Demo 图表',
                    'dataset_id' => $dataset->id,
                    'chart_type' => $definition['chart_type'],
                    'config_json' => $definition['config_json'],
                    'style_json' => ['title' => $name],
                    'status' => 'active',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        return $charts;
    }

    /**
     * @return array<string, mixed>
     */
    private function rankConfig(string $dimension, string $alias, int $limit): array
    {
        return [
            'dimensions' => [['field' => $dimension]],
            'metrics' => [['field' => 'amount', 'aggregate' => 'sum', 'alias' => $alias]],
            'sorts' => [['field' => $alias, 'direction' => 'desc']],
            'limit' => $limit,
            'use_cache' => true,
        ];
    }

    /**
     * @param  array<string, Chart>  $charts
     */
    private function seedDashboard(array $charts, User $admin): Dashboard
    {
        $dashboard = Dashboard::query()->updateOrCreate(
            ['name' => self::DASHBOARD_NAME],
            [
                'description' => '电商销售分析 Demo 看板，覆盖销售额、订单数、利润率、趋势、区域、品类和渠道。',
                'global_filters_json' => [],
                'status' => 'active',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
        DashboardFilter::query()->updateOrCreate(
            [
                'dashboard_id' => $dashboard->id,
                'field_name' => 'province',
            ],
            [
                'label' => '省份',
                'filter_type' => 'select',
                'default_value_json' => null,
                'config_json' => ['operator' => '='],
            ],
        );

        $layout = [
            ['name' => '销售额指标卡', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3],
            ['name' => '订单数指标卡', 'x' => 4, 'y' => 0, 'w' => 4, 'h' => 3],
            ['name' => '利润率指标卡', 'x' => 8, 'y' => 0, 'w' => 4, 'h' => 3],
            ['name' => '最近 12 个月销售趋势折线图', 'x' => 0, 'y' => 3, 'w' => 8, 'h' => 5],
            ['name' => '省份销售额柱状图', 'x' => 8, 'y' => 3, 'w' => 4, 'h' => 5],
            ['name' => '产品分类销售额饼图', 'x' => 0, 'y' => 8, 'w' => 4, 'h' => 5],
            ['name' => '销售渠道订单数柱状图', 'x' => 4, 'y' => 8, 'w' => 4, 'h' => 5],
            ['name' => '城市销售额排行表格', 'x' => 8, 'y' => 8, 'w' => 4, 'h' => 5],
            ['name' => '销售明细表格', 'x' => 0, 'y' => 13, 'w' => 12, 'h' => 6],
        ];
        $widgets = [];

        foreach ($layout as $index => $item) {
            $chart = $charts[$item['name']] ?? null;

            if (! $chart instanceof Chart) {
                continue;
            }

            $widgets[] = DashboardWidget::query()->updateOrCreate(
                [
                    'dashboard_id' => $dashboard->id,
                    'chart_id' => $chart->id,
                ],
                [
                    'widget_type' => 'chart',
                    'x' => $item['x'],
                    'y' => $item['y'],
                    'w' => $item['w'],
                    'h' => $item['h'],
                    'config_json' => [],
                    'sort_order' => $index + 1,
                ],
            );
        }

        $dashboard->forceFill([
            'layout_json' => collect($widgets)->map(fn (DashboardWidget $widget): array => [
                'widget_id' => $widget->id,
                'x' => $widget->x,
                'y' => $widget->y,
                'w' => $widget->w,
                'h' => $widget->h,
            ])->values()->all(),
        ])->save();

        return $dashboard->refresh();
    }

    private function seedDataPermissions(Dataset $dataset, User $viewer): void
    {
        DataPermissionRule::query()->updateOrCreate(
            [
                'dataset_id' => $dataset->id,
                'subject_type' => 'user',
                'subject_id' => $viewer->id,
                'field_name' => 'province',
            ],
            [
                'operator' => '=',
                'value_type' => 'static',
                'value_json' => ['value' => '广东'],
                'status' => 'active',
            ],
        );
        ColumnPermissionRule::query()->updateOrCreate(
            [
                'dataset_id' => $dataset->id,
                'subject_type' => 'user',
                'subject_id' => $viewer->id,
                'field_name' => 'customer_name',
                'permission_type' => 'hidden',
            ],
        );
    }

    /**
     * @return array{built: bool, status: string, detail_rows: ?int, aggregate_rows: ?int, error: ?string}
     */
    private function seedClickHouseAcceleration(Dataset $dataset, User $admin): array
    {
        $skipped = $this->clickHouseAccelerationSkipReason();

        if ($skipped !== null) {
            return [
                'built' => false,
                'status' => 'skipped: '.$skipped,
                'detail_rows' => null,
                'aggregate_rows' => null,
                'error' => null,
            ];
        }

        try {
            $this->useShortClickHouseTimeout();

            if (! app(ClickHouseClient::class)->ping()) {
                return [
                    'built' => false,
                    'status' => 'skipped: ClickHouse is not reachable',
                    'detail_rows' => null,
                    'aggregate_rows' => null,
                    'error' => null,
                ];
            }

            $detailProfile = $this->buildClickHouseDetailAcceleration($dataset, $admin);
            $aggregateDefinition = $this->buildClickHouseAggregateAcceleration($dataset, $detailProfile, $admin);

            return [
                'built' => true,
                'status' => 'built',
                'detail_rows' => $detailProfile->row_count,
                'aggregate_rows' => $aggregateDefinition->row_count,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'built' => false,
                'status' => 'failed',
                'detail_rows' => null,
                'aggregate_rows' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function clickHouseAccelerationSkipReason(): ?string
    {
        if (! (bool) config('bi_acceleration.enabled', true)) {
            return 'acceleration is disabled';
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return 'current database connection is not mysql';
        }

        if (! Schema::hasTable('acceleration_profiles') || ! Schema::hasTable('acceleration_aggregate_definitions')) {
            return 'acceleration tables are not migrated';
        }

        return null;
    }

    private function useShortClickHouseTimeout(): void
    {
        $timeout = (int) config('bi_acceleration.clickhouse.timeout', 30);

        config(['bi_acceleration.clickhouse.timeout' => max(1, min($timeout, 3))]);
    }

    private function buildClickHouseDetailAcceleration(Dataset $dataset, User $admin): AccelerationProfile
    {
        $payload = [
            'dataset_id' => $dataset->id,
            'name' => self::DETAIL_ACCELERATION_NAME,
            'engine_type' => 'clickhouse',
            'mode' => 'detail_table',
            'status' => 'disabled',
            'source_connection_id' => $dataset->data_source_id,
            'target_database' => (string) config('bi_acceleration.clickhouse.database', 'bi_accelerator'),
            'target_table' => self::DETAIL_ACCELERATION_TABLE,
            'refresh_type' => 'manual',
            'columns' => app(AccelerationSchemaService::class)->preview($dataset),
        ];
        $service = app(AccelerationProfileService::class);
        $profile = AccelerationProfile::query()
            ->where('dataset_id', $dataset->id)
            ->where('target_table', self::DETAIL_ACCELERATION_TABLE)
            ->first();

        $profile = $profile instanceof AccelerationProfile
            ? $service->update($profile, $payload)
            : $service->create($payload, $admin);

        $profile->forceFill([
            'status' => 'building',
            'last_error_message' => null,
        ])->save();

        $task = $profile->tasks()->create([
            'task_type' => 'full_sync',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        app(AccelerationSyncService::class)->process($task->id);

        return $profile->refresh()->load('columns');
    }

    private function buildClickHouseAggregateAcceleration(Dataset $dataset, AccelerationProfile $detailProfile, User $admin): AccelerationAggregateDefinition
    {
        $payload = [
            'dataset_id' => $dataset->id,
            'detail_profile_id' => $detailProfile->id,
            'name' => self::AGGREGATE_ACCELERATION_NAME,
            'status' => 'disabled',
            'target_database' => (string) config('bi_acceleration.clickhouse.database', 'bi_accelerator'),
            'target_table' => self::AGGREGATE_ACCELERATION_TABLE,
            'time_field' => 'order_date',
            'time_grain' => 'month',
            'dimensions' => ['province', 'product_category', 'channel'],
            'metrics' => [
                ['field' => 'amount', 'aggregate' => 'sum', 'alias' => 'amount_sum'],
                ['field' => 'order_no', 'aggregate' => 'count', 'alias' => 'order_count'],
                ['field' => 'profit_amount', 'aggregate' => 'sum', 'alias' => 'profit_amount_sum'],
            ],
            'refresh_type' => 'manual',
        ];
        $service = app(AggregateDefinitionService::class);
        $definition = AccelerationAggregateDefinition::query()
            ->where('dataset_id', $dataset->id)
            ->where('target_table', self::AGGREGATE_ACCELERATION_TABLE)
            ->first();

        $definition = $definition instanceof AccelerationAggregateDefinition
            ? $service->update($definition, $payload)
            : $service->create($payload, $admin, $dataset);

        $definition->loadMissing('aggregateProfile');
        $profile = $definition->aggregateProfile;

        if (! $profile instanceof AccelerationProfile) {
            throw new \RuntimeException('Aggregate acceleration profile is missing.');
        }

        $definition->forceFill([
            'status' => 'building',
            'last_error_message' => null,
        ])->save();
        $profile->forceFill([
            'status' => 'building',
            'last_error_message' => null,
        ])->save();

        $task = $profile->tasks()->create([
            'task_type' => 'build_aggregate',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        app(AggregateBuildService::class)->process($task->id);

        return $definition->refresh()->load('columns');
    }
}
