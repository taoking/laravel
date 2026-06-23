继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 10：BI 查询加速方案

## 一、当前项目背景

当前项目是 Laravel 13 + Docker 构建的 BI 分析工具。

已完成或已有能力包括：

1. 后端 API 主体。
2. Vue 前端工程或前端计划。
3. 数据源管理。
4. 数据集建模。
5. 查询引擎。
6. 图表管理。
7. 仪表盘管理。
8. 导入导出。
9. 查询缓存。
10. 数据权限。
11. 查询日志。
12. Prometheus metrics。
13. 健康检查接口。

当前边界：

1. Laravel/MySQL 主要作为系统元数据存储。
2. 导入生成的物理表目前写入当前应用数据库。
3. 查询引擎目前主要面向关系型数据库生成 SQL。
4. 仪表盘 PDF 是轻量文本导出。
5. Grafana/Loki 属于部署层增强。
6. 本阶段目标是增加 BI 查询加速方案，不是重写整个 BI 系统。

## 二、本阶段目标

为当前 BI 系统增加一套可扩展的查询加速架构。

核心目标：

1. 支持列式数据库作为查询加速层。
2. 支持将导入数据或指定数据集同步到分析库。
3. 支持加速表、宽表、预聚合表、物化视图的元数据管理。
4. 支持查询引擎根据规则选择加速层。
5. 支持查询失败时回退原始数据源。
6. 支持记录加速命中情况。
7. 支持对比原始查询和加速查询耗时。
8. 支持后续扩展 ClickHouse / StarRocks / Doris。
9. 第一版优先落地 ClickHouse 或通用 ColumnarDriver 抽象。
10. 不破坏已有数据源、数据集、图表、仪表盘、权限、缓存、日志主流程。

## 三、总体架构设计

目标架构：

```text
Laravel BI System
    |
    |-- MySQL
    |     |-- 系统元数据
    |     |-- 用户/权限
    |     |-- 数据源配置
    |     |-- 数据集配置
    |     |-- 图表配置
    |     |-- 仪表盘配置
    |     |-- 查询日志
    |
    |-- Redis
    |     |-- 查询结果缓存
    |     |-- 加速任务状态
    |     |-- 分布式锁
    |
    |-- Columnar Database
    |     |-- ClickHouse / StarRocks / Doris
    |     |-- 明细宽表
    |     |-- 汇总表
    |     |-- 预聚合表
    |     |-- 物化视图
    |
    |-- Query Acceleration Layer
          |-- 加速规则
          |-- 查询路由
          |-- SQL 方言适配
          |-- 数据同步任务
          |-- 加速命中日志
          |-- fallback 回退机制
```

查询流程：

```text
前端图表查询
    ↓
Chart Query API
    ↓
QueryService
    ↓
读取数据集元数据
    ↓
读取用户权限规则
    ↓
生成逻辑查询计划 LogicalQueryPlan
    ↓
判断是否存在可用加速方案
    ↓
命中加速方案？
    ├── 是：生成 ClickHouse / StarRocks / Doris SQL
    │       ↓
    │     查询分析库
    │       ↓
    │     写入 query_logs acceleration_hit = true
    │       ↓
    │     返回图表数据
    │
    └── 否：走原始数据源 SQL
            ↓
          写入 query_logs acceleration_hit = false
            ↓
          返回图表数据
```

## 四、加速方案分层

本阶段需要把查询加速拆成 4 层，不要写死在 QueryService 里。

### 1. 查询结果缓存层

适合：

1. 同一个用户重复刷新图表。
2. 仪表盘短时间内频繁打开。
3. 查询结果允许短时间延迟。

已有 Redis 查询缓存可以复用。

增强点：

1. 缓存 key 增加 acceleration_hit。
2. 缓存 key 增加 acceleration_version。
3. 图表结果缓存需要区分原始数据源结果和加速层结果。
4. 加速表刷新后需要清理相关图表缓存。

### 2. 原始库优化层

适合：

1. 小数据量。
2. 低频查询。
3. 不值得同步到分析库的数据集。

可支持：

1. 索引建议。
2. explain 分析。
3. 慢查询记录。
4. 大表提醒。
5. 查询 limit 保护。
6. 超时保护。

### 3. 列式数据库明细层

适合：

1. 大宽表。
2. 明细数据量大。
3. 按时间、地区、组织、用户等维度筛选。
4. 多维聚合。

做法：

1. 将数据集物理数据同步到列式数据库。
2. 在列式库中创建明细表。
3. 查询引擎把 SQL 路由到列式表。
4. 图表聚合查询直接在列式库执行。

### 4. 预聚合 / 物化视图层

适合：

1. 高频仪表盘。
2. 固定维度组合。
3. 固定指标统计。
4. 大屏类场景。
5. 按天、月、地区、部门的固定聚合。

做法：

1. 为数据集创建加速视图或汇总表。
2. 按常见维度提前 group by。
3. 查询时优先命中预聚合表。
4. 不满足预聚合维度时，回退明细表或原始数据源。

## 五、第一版推荐落地范围

第一版不要同时接 ClickHouse、StarRocks、Doris 三套。

优先做成：

```text
通用加速抽象 + ClickHouse 最小落地
```

第一版实现范围：

1. 新增加速数据源类型：clickhouse。
2. docker-compose 可选增加 clickhouse 服务。
3. 新增加速连接配置。
4. 支持把导入数据表同步到 ClickHouse 明细表。
5. 支持为数据集创建 acceleration_profile。
6. 支持图表查询时判断是否走 ClickHouse。
7. 支持 ClickHouse SQL Driver。
8. 支持记录 acceleration_hit。
9. 支持 fallback 到原始 MySQL 查询。
10. 支持加速任务日志。
11. 支持 README 文档说明。

暂不强制实现：

1. 完整 CDC。
2. Kafka / Debezium。
3. Flink。
4. 多节点 ClickHouse 集群。
5. Doris / StarRocks 真正落地。
6. 自动物化视图推荐算法。
7. 复杂 SQL 优化器。
8. 跨库 Join 自动改写。

## 六、数据库表设计建议

### 1. acceleration_profiles

用于描述一个数据集是否启用查询加速。

字段建议：

```text
id
dataset_id
name
engine_type              clickhouse / starrocks / doris / mysql_summary
mode                     detail_table / aggregate_table / materialized_view
status                   disabled / building / active / failed
source_connection_id
target_connection_id
target_database
target_table
refresh_type             manual / scheduled / on_import_completed
refresh_interval_minutes
last_refresh_at
last_success_at
last_error_message
row_count
version
config_json
created_at
updated_at
```

说明：

1. 一个 dataset 可以有多个 acceleration_profile。
2. active 的 profile 才能被查询路由使用。
3. version 用于缓存失效。
4. config_json 保存引擎特定配置。

### 2. acceleration_columns

用于记录加速表字段映射。

字段建议：

```text
id
acceleration_profile_id
dataset_field_id
source_field_name
target_field_name
source_type
target_type
is_dimension
is_metric
aggregate_functions_json
is_partition_key
is_order_key
is_nullable
created_at
updated_at
```

说明：

1. 记录 MySQL 字段到 ClickHouse 字段的映射。
2. 标记维度、指标、分区字段、排序字段。
3. 后续用于生成建表 SQL。

### 3. acceleration_tasks

用于记录同步任务。

字段建议：

```text
id
acceleration_profile_id
task_type                full_sync / incremental_sync / refresh_mv / rebuild
status                   pending / running / success / failed
started_at
finished_at
source_row_count
target_row_count
duration_ms
error_message
logs_json
created_by
created_at
updated_at
```

### 4. acceleration_query_logs

可以单独建表，也可以扩展 query_logs。

如果扩展 query_logs，建议增加字段：

```text
acceleration_hit
acceleration_profile_id
acceleration_engine
acceleration_mode
fallback_used
fallback_reason
source_duration_ms
accelerated_duration_ms
```

如果单独建 acceleration_query_logs，字段建议：

```text
id
query_log_id
dataset_id
chart_id
dashboard_id
acceleration_profile_id
engine_type
mode
hit
fallback_used
fallback_reason
original_sql
accelerated_sql
duration_ms
created_at
```

## 七、配置文件建议

新增配置：

```text
config/bi_acceleration.php
```

配置内容：

```php
return [
    'enabled' => env('BI_ACCELERATION_ENABLED', true),

    'default_engine' => env('BI_ACCELERATION_ENGINE', 'clickhouse'),

    'query' => [
        'fallback_on_error' => true,
        'max_rows_for_original_query' => 100000,
        'slow_query_threshold_ms' => 3000,
        'compare_original_query' => false,
    ],

    'sync' => [
        'chunk_size' => 5000,
        'max_retry' => 3,
        'timeout_seconds' => 600,
    ],

    'clickhouse' => [
        'connection' => env('BI_CLICKHOUSE_CONNECTION', 'clickhouse'),
        'database' => env('BI_CLICKHOUSE_DATABASE', 'bi_accelerator'),
    ],
];
```

.env.example 增加：

```text
BI_ACCELERATION_ENABLED=true
BI_ACCELERATION_ENGINE=clickhouse
BI_CLICKHOUSE_HOST=clickhouse
BI_CLICKHOUSE_PORT=8123
BI_CLICKHOUSE_DATABASE=bi_accelerator
BI_CLICKHOUSE_USERNAME=default
BI_CLICKHOUSE_PASSWORD=
```

## 八、服务类设计

建议新增模块：

```text
app/Modules/Acceleration/
```

或者根据当前项目结构放到：

```text
app/Services/Acceleration/
```

核心类：

```text
AccelerationProfileService
AccelerationSchemaService
AccelerationSyncService
AccelerationQueryRouter
AccelerationSqlGenerator
AccelerationEligibilityChecker
AccelerationFallbackService
AccelerationMetricsService
```

Driver 抽象：

```text
app/Modules/Acceleration/Drivers/
  AccelerationDriverInterface.php
  ClickHouseAccelerationDriver.php
  StarRocksAccelerationDriver.php
  DorisAccelerationDriver.php
  MysqlSummaryAccelerationDriver.php
```

第一版只实现：

```text
AccelerationDriverInterface
ClickHouseAccelerationDriver
MysqlSummaryAccelerationDriver 可选
```

接口建议：

```php
interface AccelerationDriverInterface
{
    public function testConnection(): bool;

    public function createDatabaseIfNotExists(string $database): void;

    public function createDetailTable(AccelerationProfile $profile): void;

    public function dropTable(AccelerationProfile $profile): void;

    public function insertRows(AccelerationProfile $profile, iterable $rows): int;

    public function generateQuerySql(LogicalQueryPlan $plan, AccelerationProfile $profile): GeneratedSql;

    public function supports(LogicalQueryPlan $plan, AccelerationProfile $profile): bool;
}
```

## 九、逻辑查询计划 LogicalQueryPlan

不要让查询引擎直接围绕 MySQL SQL 字符串做改写。

建议新增一个中间结构：

```text
LogicalQueryPlan
```

包含：

```text
dataset_id
dimensions
metrics
filters
permission_filters
sorts
limit
offset
time_grain
chart_id
dashboard_id
user_id
```

流程：

```text
前端查询配置
    ↓
Query DTO
    ↓
LogicalQueryPlan
    ↓
MySqlSqlGenerator 或 ClickHouseSqlGenerator
```

好处：

1. 同一套图表配置可以生成不同数据库方言 SQL。
2. 后续更容易支持 Doris / StarRocks。
3. 可以在 LogicalQueryPlan 层判断是否适合加速。
4. 可以在 LogicalQueryPlan 层做权限合并。

## 十、查询路由规则

新增：

```text
AccelerationEligibilityChecker
```

判断是否适合加速。

第一版规则：

可以走加速层的条件：

1. dataset 已启用 active acceleration_profile。
2. 该 profile 目标表存在。
3. 查询字段都能在 acceleration_columns 找到映射。
4. filter 字段都能映射。
5. sort 字段都能映射。
6. 当前查询不包含加速层不支持的函数。
7. 当前用户权限 filter 可以被加速层表达。
8. 当前图表查询不是原始明细预览类强一致查询。

不能走加速层的情况：

1. profile 未启用。
2. 加速任务正在 building。
3. 加速任务失败。
4. 字段映射缺失。
5. SQL 表达式字段无法转换。
6. 当前查询要求实时强一致。
7. 数据权限条件无法转换。
8. 加速层连接失败。

路由结果：

```text
AccelerationRouteDecision
```

字段：

```text
use_acceleration
profile_id
engine_type
mode
reason
fallback_allowed
```

## 十一、ClickHouse 第一版实现要求

### 1. Docker Compose

如果当前 docker-compose.yml 结构清晰，可以增加可选服务：

```text
clickhouse:
  image: clickhouse/clickhouse-server
  ports:
    - "8123:8123"
    - "9000:9000"
  volumes:
    - clickhouse_data:/var/lib/clickhouse
```

如果项目环境暂不适合直接加容器，则只补充 docker-compose.clickhouse.yml。

不要影响现有 Laravel、MySQL、Redis、MinIO、Queue Worker。

### 2. Laravel 连接

在 config/database.php 增加 clickhouse 连接配置。

如果没有合适的 Laravel ClickHouse 包，第一版可以先用 HTTP Client 方式执行 ClickHouse SQL。

要求：

1. 连接配置放 .env。
2. 不要把账号密码写死。
3. SQL 执行封装在 ClickHouseClient。
4. 不要在业务 Service 里直接拼 HTTP 请求。

### 3. 类型映射

MySQL 到 ClickHouse 类型映射建议：

```text
int / integer        -> Int64
bigint               -> Int64
decimal              -> Decimal(18, 4)
float / double       -> Float64
varchar / text       -> String
date                 -> Date
datetime / timestamp -> DateTime
tinyint              -> Int8
boolean              -> UInt8
json                 -> String
```

注意：

1. nullable 字段映射为 Nullable(Type)。
2. 金额字段尽量用 Decimal。
3. 时间字段适合作为分区字段。
4. 低基数字符串字段后续可考虑 LowCardinality(String)，第一版可不做。

### 4. 建表策略

第一版明细表建议：

```text
MergeTree
PARTITION BY toYYYYMM(date_field)
ORDER BY (date_field, common_dimension_1, common_dimension_2)
```

如果没有时间字段：

```text
MergeTree
ORDER BY tuple()
```

更好的做法：

1. 优先选择日期字段作为分区键。
2. 优先选择高频过滤维度作为排序键。
3. 避免 ORDER BY 过多字段。
4. 建表策略写入 config_json，方便后续调整。

### 5. 数据同步

第一版实现全量同步即可。

流程：

```text
用户点击构建加速表
    ↓
创建 acceleration_task
    ↓
状态 running
    ↓
读取 dataset 对应源表数据
    ↓
按 chunk 分批读取
    ↓
写入 ClickHouse
    ↓
记录 row_count
    ↓
状态 success
    ↓
profile 状态 active
```

要求：

1. 使用 Laravel Queue。
2. 分批读取，避免一次加载所有数据。
3. 同步失败要记录 error_message。
4. 任务可重试。
5. 同步过程中 profile 状态为 building。
6. 同步成功后清理相关图表缓存。

### 6. 查询生成

ClickHouse SQL 生成需要支持：

1. select dimensions
2. aggregate metrics
3. where filters
4. group by
5. order by
6. limit
7. offset 可选
8. 时间颗粒：year、quarter、month、week、day

时间颗粒函数建议：

```text
year    -> toYear(date_field)
month   -> toStartOfMonth(date_field)
day     -> toDate(date_field)
hour    -> toStartOfHour(date_field)
```

聚合函数支持：

```text
sum
avg
count
countDistinct
min
max
```

所有字段名必须来自 acceleration_columns 映射。
所有值必须通过安全参数处理或严格转义，不允许直接拼接用户输入。

## 十二、物化视图 / 预聚合设计

第一版可以先做文档和元数据预留，不强制完整自动创建。

建议支持两类：

### 1. 高频图表预聚合表

根据图表配置生成聚合表：

```text
chart_{chart_id}_agg_{hash}
```

适合：

1. 固定图表。
2. 高频仪表盘。
3. 指标卡。
4. 趋势图。
5. 地区排行。

字段：

```text
dimension fields
metric aggregate fields
time_grain
updated_at
```

### 2. 数据集通用汇总表

根据常见维度组合生成：

```text
dataset_{dataset_id}_daily_summary
dataset_{dataset_id}_monthly_summary
dataset_{dataset_id}_region_summary
```

适合：

1. 日期趋势。
2. 地区统计。
3. 部门统计。
4. 常见管理看板。

### 3. 自动推荐暂不实现

只预留：

```text
AccelerationRecommendationService
```

后续可以根据 query_logs 分析：

1. 哪些图表最慢。
2. 哪些查询最频繁。
3. 哪些维度组合最常见。
4. 哪些指标最常聚合。

然后推荐创建加速表或物化视图。

## 十三、API 设计建议

新增接口前请先检查项目已有路由风格。

建议接口：

```text
GET    /api/acceleration/profiles
POST   /api/acceleration/profiles
GET    /api/acceleration/profiles/{id}
PUT    /api/acceleration/profiles/{id}
DELETE /api/acceleration/profiles/{id}

POST   /api/acceleration/profiles/{id}/test
POST   /api/acceleration/profiles/{id}/build
POST   /api/acceleration/profiles/{id}/refresh
POST   /api/acceleration/profiles/{id}/disable
POST   /api/acceleration/profiles/{id}/activate

GET    /api/acceleration/tasks
GET    /api/acceleration/tasks/{id}

GET    /api/datasets/{dataset}/acceleration
POST   /api/datasets/{dataset}/acceleration/build
GET    /api/datasets/{dataset}/acceleration/columns
```

返回内容需要包括：

1. profile 状态。
2. engine_type。
3. mode。
4. target_table。
5. row_count。
6. last_refresh_at。
7. last_error_message。
8. 最近任务状态。

## 十四、前端页面建议

如果 Vue 前端已落地，增加页面：

```text
查询加速管理
```

页面包括：

1. 数据集加速状态列表。
2. 创建加速配置。
3. 选择加速引擎。
4. 字段映射预览。
5. 分区键选择。
6. 排序键选择。
7. 构建加速表按钮。
8. 刷新加速表按钮。
9. 禁用加速按钮。
10. 最近同步任务列表。
11. 加速命中日志。
12. 原始查询耗时 vs 加速查询耗时。

如果 Vue 前端未完成，则只实现后端 API 和文档。

## 十五、查询日志增强

扩展 query_logs：

建议增加：

```text
acceleration_hit
acceleration_profile_id
acceleration_engine
acceleration_mode
fallback_used
fallback_reason
source_duration_ms
accelerated_duration_ms
```

记录规则：

1. 走加速层成功：acceleration_hit = true。
2. 原本可走加速但失败回退：fallback_used = true。
3. 不符合加速规则：acceleration_hit = false，fallback_reason 记录原因。
4. 加速查询失败时，记录错误信息。
5. 缓存命中时也要保留是否来自加速结果。

## 十六、缓存失效

加速层引入后，需要调整缓存失效。

触发失效的情况：

1. acceleration_profile active 状态变化。
2. 加速表重建成功。
3. 加速表刷新成功。
4. 字段映射变化。
5. 数据集结构变化。
6. 图表配置变化。
7. 权限规则变化。

建议缓存 key 加上：

```text
acceleration_profile_id
acceleration_version
```

例如：

```text
bi:chart:{chart_id}:user:{user_id}:acc:{profile_id}:v:{version}:query:{query_hash}
```

## 十七、权限要求

加速层不能绕过现有权限。

必须满足：

1. 资源权限仍然先检查。
2. 行级数据权限仍然合并到查询条件。
3. 列级权限仍然过滤返回字段。
4. 数据权限字段必须能映射到加速表字段。
5. 不能映射时必须回退原始查询或拒绝查询。
6. 不允许前端指定绕过加速层权限。
7. 不允许前端直接传 ClickHouse 表名。

## 十八、稳定性要求

必须支持 fallback。

场景：

1. ClickHouse 连接失败。
2. 加速表不存在。
3. 加速 profile 状态不是 active。
4. 字段映射缺失。
5. SQL 生成失败。
6. 查询执行失败。
7. 数据同步任务失败。

处理方式：

1. 如果配置允许 fallback，则回退原始数据源查询。
2. 如果配置不允许 fallback，则返回清晰错误。
3. query_logs 记录 fallback_reason。
4. 不要让图表页面直接崩溃。
5. 不要吞掉异常不记录。

## 十九、验收标准

完成后需要满足：

1. 可以创建 dataset 的 acceleration_profile。
2. 可以通过 API 构建 ClickHouse 加速表。
3. 可以把导入数据或指定数据集同步到 ClickHouse。
4. 同步任务有状态记录。
5. 图表查询可以在符合条件时自动走加速层。
6. 图表查询不符合条件时可以回退原始查询。
7. query_logs 能记录 acceleration_hit。
8. 查询结果缓存能区分原始查询和加速查询。
9. 权限过滤在加速查询中仍然生效。
10. 加速层失败不会导致系统不可用。
11. README 说明如何启动 ClickHouse。
12. README 说明如何创建加速配置。
13. README 说明当前加速方案边界。
14. 不破坏原有 API、测试和 Docker 环境。
15. 尽量运行 php artisan test。
16. 尽量运行 php artisan route:list。
17. 如果有前端，前端 build 不能失败。

## 二十、测试数据建议

可以新增一组 seed 或文档说明：

数据集：sales_orders

字段：

```text
id
order_no
province
city
department_id
sales_user_id
customer_name
product_category
amount
quantity
order_date
created_at
```

测试查询：

1. 按月份统计销售额。
2. 按省份统计销售额。
3. 按产品分类统计订单数。
4. 按部门统计销售额。
5. 最近 30 天销售趋势。
6. 广东省销售额排行。
7. 指标卡：总销售额、订单数、客单价。

对比：

1. 原始 MySQL 查询耗时。
2. ClickHouse 查询耗时。
3. Redis 缓存命中耗时。
4. fallback 场景。

## 二十一、文档要求

新增或更新：

```text
docs/bi-acceleration.md
```

内容包括：

1. 查询加速总体架构。
2. 为什么需要列式数据库。
3. MySQL、Redis、ClickHouse 的职责边界。
4. acceleration_profiles 表说明。
5. 加速表构建流程。
6. 查询路由流程。
7. fallback 机制。
8. 权限如何生效。
9. 缓存 key 设计。
10. query_logs 字段说明。
11. Docker 启动 ClickHouse。
12. 当前实现边界。
13. 后续如何扩展 StarRocks / Doris。
14. 生产环境建议。

生产环境建议包括：

1. ClickHouse 独立集群，不和 Laravel 应用混部。
2. MySQL 继续存系统元数据。
3. 大数据导入或同步使用异步队列。
4. 高频固定图表使用预聚合表。
5. 对实时性要求高的数据，使用增量同步或 CDC。
6. 对一致性要求高的查询，允许回退原始库。
7. 监控 ClickHouse 查询耗时、失败率、磁盘、内存。
8. 慢查询通过 query_logs 分析。
9. 根据 query_logs 选择物化视图和汇总表。
10. 不要把所有数据集都无脑同步到分析库。

## 二十二、执行顺序

请按以下顺序执行：

第一步：现状扫描

1. 阅读 plan.md。
2. 阅读当前 QueryService / QueryEngine。
3. 阅读 Dataset / Chart / Dashboard 相关代码。
4. 阅读 query_logs 和缓存相关代码。
5. 阅读 database migrations。
6. 阅读 docker-compose.yml。
7. 确认项目目录结构。

第二步：设计加速模块

1. 新增配置文件。
2. 新增 migration。
3. 新增 Model。
4. 新增 Service。
5. 新增 Driver interface。
6. 新增 ClickHouse driver 或 client。

第三步：实现加速 profile 管理

1. CRUD。
2. 状态管理。
3. 字段映射。
4. 测试连接。
5. 构建任务入口。

第四步：实现同步任务

1. Queue Job。
2. 全量同步。
3. 分批读取。
4. 写入 ClickHouse。
5. 状态记录。
6. 错误记录。
7. 缓存失效。

第五步：实现查询路由

1. LogicalQueryPlan。
2. EligibilityChecker。
3. RouteDecision。
4. ClickHouse SQL Generator。
5. fallback。
6. query_logs 增强。

第六步：实现 API

1. acceleration profiles。
2. acceleration tasks。
3. dataset acceleration。
4. route:list 验证。

第七步：文档和测试

1. docs/bi-acceleration.md。
2. README 更新。
3. .env.example 更新。
4. php artisan migrate。
5. php artisan route:list。
6. php artisan test。
7. 手工验证查询加速流程。

## 二十三、代码要求

1. 不要大范围重构已有业务。
2. 不要破坏原有查询引擎。
3. 加速逻辑必须是可选增强。
4. 加速失败必须允许 fallback。
5. 不允许前端直接传目标物理表名。
6. 不允许用户输入直接拼接 SQL。
7. 字段名必须来自元数据映射。
8. 权限 filter 必须在加速查询中生效。
9. 加速表同步必须走队列。
10. migration 必须可回滚。
11. 配置必须走 env。
12. 文档必须说明边界。

## 二十四、当前阶段不做的内容

本阶段不做：

1. 完整实时 CDC。
2. Kafka / Debezium / Flink。
3. 多节点 ClickHouse 集群。
4. StarRocks / Doris 的完整落地。
5. 自动物化视图推荐算法。
6. 复杂 SQL 优化器。
7. 跨数据源 Join 自动加速。
8. 像商业 BI 一样完整的拖拽建模优化器。
9. 大屏像素级截图导出。
10. 生产级容量压测平台。

这些只在文档中作为后续扩展说明。

## 二十五、最终输出要求

任务完成后，请输出：

1. 本次完成内容。
2. 修改文件列表。
3. 新增数据库表。
4. 新增配置文件。
5. 新增 Service / Driver / Job。
6. 新增 API 列表。
7. ClickHouse Docker 使用方式。
8. 加速表构建流程。
9. 查询路由流程。
10. fallback 机制说明。
11. query_logs 增强字段说明。
12. 缓存 key 变化说明。
13. 运行过的命令。
14. 测试结果。
15. 当前实现边界。
16. 遗留问题。
17. 下一阶段建议。