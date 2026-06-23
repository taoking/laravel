继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 10.1：ClickHouse 查询加速最小闭环

## 一、当前项目背景

当前项目是 Laravel 13 + Docker 构建的 BI 分析工具。

已有能力包括：

1. 数据源管理
2. 数据集建模
3. 查询引擎
4. 图表管理
5. 仪表盘管理
6. 导入导出
7. 查询缓存
8. 数据权限
9. 查询日志
10. Prometheus metrics
11. 健康检查接口
12. Vue 前端工程，或已有前端计划

当前目标不是重写 BI 系统，而是在现有查询链路上增加一层可选的 ClickHouse 查询加速能力。

本阶段只做最小闭环，不做完整商业级加速平台。

## 二、本阶段目标

实现一套最小可用的 ClickHouse 查询加速闭环：

1. Docker 环境增加 ClickHouse 服务。
2. Laravel 增加 ClickHouse 连接配置。
3. 新增加速配置表 acceleration_profiles。
4. 新增加速字段映射表 acceleration_columns。
5. 新增加速同步任务表 acceleration_tasks。
6. 支持为某个 dataset 创建 ClickHouse 加速配置。
7. 支持把 dataset 对应的数据同步到 ClickHouse 明细表。
8. 图表查询时自动判断是否可以走 ClickHouse。
9. 命中条件时走 ClickHouse 查询。
10. 不命中或失败时 fallback 回原始查询。
11. query_logs 记录是否命中加速层。
12. 更新文档说明当前边界。

## 三、本阶段明确不做

本阶段不要做以下内容：

1. 不做 Kafka / Debezium / Flink。
2. 不做实时 CDC。
3. 不做 StarRocks / Doris 完整接入。
4. 不做复杂 SQL 优化器。
5. 不做自动物化视图推荐。
6. 不做多节点 ClickHouse 集群。
7. 不做复杂预聚合推荐。
8. 不重写现有查询引擎。
9. 不重写权限系统。
10. 不重写导入导出流程。

本阶段只做：

```text
ClickHouse 明细表加速 + 查询路由 + fallback + 日志
```

## 四、执行前先扫描

请先阅读：

1. plan.md
2. routes/api.php
3. 当前 QueryService / QueryEngine / QueryBuilder 相关代码
4. Dataset / DatasetField 相关 Model 和 migration
5. Chart 查询接口
6. query_logs 表和相关写入逻辑
7. 缓存相关 Service
8. 权限相关 Service
9. docker-compose.yml
10. config/database.php
11. .env.example
12. README.md

要求：

1. 先理解现有查询链路。
2. 不要凭空新建一套完全独立的查询系统。
3. 优先复用现有 Dataset、Chart、QueryService。
4. 加速层作为可选增强接入。

## 五、数据库表设计

### 1. acceleration_profiles

新增 migration：

```text
acceleration_profiles
```

字段建议：

```text
id
dataset_id
name
engine_type              clickhouse
mode                     detail_table
status                   disabled / building / active / failed
target_database
target_table
refresh_type             manual
last_refresh_at
last_success_at
last_error_message
row_count
version
config_json
created_at
updated_at
```

要求：

1. dataset_id 外键关联 datasets。
2. engine_type 第一版只支持 clickhouse。
3. mode 第一版只支持 detail_table。
4. status 默认 disabled。
5. version 默认 1。
6. config_json 用于保存分区键、排序键等配置。

### 2. acceleration_columns

新增 migration：

```text
acceleration_columns
```

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
is_partition_key
is_order_key
is_nullable
created_at
updated_at
```

要求：

1. 记录 dataset 字段到 ClickHouse 字段的映射。
2. target_field_name 必须安全生成，不允许使用危险字符。
3. 后续 ClickHouse SQL 只能使用这里的字段映射。

### 3. acceleration_tasks

新增 migration：

```text
acceleration_tasks
```

字段建议：

```text
id
acceleration_profile_id
task_type                full_sync / rebuild
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

要求：

1. 记录同步任务状态。
2. 同步失败要记录 error_message。
3. 同步成功要更新 profile 状态和 row_count。

### 4. query_logs 增强

如果已有 query_logs 表，请新增字段：

```text
acceleration_hit
acceleration_profile_id
acceleration_engine
fallback_used
fallback_reason
accelerated_duration_ms
```

要求：

1. 不破坏已有 query_logs 写入逻辑。
2. 字段允许 nullable。
3. 查询未走加速时 acceleration_hit = false。
4. 走加速成功时 acceleration_hit = true。
5. 加速失败回退时 fallback_used = true。

## 六、配置文件

新增：

```text
config/bi_acceleration.php
```

内容建议：

```php
return [
    'enabled' => env('BI_ACCELERATION_ENABLED', true),

    'default_engine' => env('BI_ACCELERATION_ENGINE', 'clickhouse'),

    'query' => [
        'fallback_on_error' => true,
        'slow_query_threshold_ms' => 3000,
    ],

    'sync' => [
        'chunk_size' => 5000,
        'max_retry' => 3,
        'timeout_seconds' => 600,
    ],

    'clickhouse' => [
        'host' => env('BI_CLICKHOUSE_HOST', 'clickhouse'),
        'port' => env('BI_CLICKHOUSE_PORT', 8123),
        'database' => env('BI_CLICKHOUSE_DATABASE', 'bi_accelerator'),
        'username' => env('BI_CLICKHOUSE_USERNAME', 'default'),
        'password' => env('BI_CLICKHOUSE_PASSWORD', ''),
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

## 七、Docker 要求

如果当前 docker-compose.yml 结构清晰，请增加 ClickHouse 服务：

```yaml
clickhouse:
  image: clickhouse/clickhouse-server:latest
  ports:
    - "8123:8123"
    - "9000:9000"
  volumes:
    - clickhouse_data:/var/lib/clickhouse
  environment:
    CLICKHOUSE_DB: bi_accelerator
    CLICKHOUSE_USER: default
    CLICKHOUSE_PASSWORD: ""
```

同时增加 volume：

```yaml
clickhouse_data:
```

要求：

1. 不影响现有 Laravel、MySQL、Redis、MinIO、Queue Worker。
2. 如果直接修改 docker-compose.yml 风险较大，可以新增 docker-compose.clickhouse.yml。
3. README 需要说明如何启动 ClickHouse。

## 八、ClickHouse Client

新增：

```text
app/Services/Acceleration/ClickHouseClient.php
```

或者按当前项目目录结构放置。

第一版可以使用 Laravel HTTP Client 调 ClickHouse HTTP 接口。

需要支持：

```php
execute(string $sql): array
select(string $sql): array
insert(string $table, array $rows): int
ping(): bool
```

要求：

1. 连接信息来自 config/bi_acceleration.php。
2. 不要在业务代码中直接写 HTTP 请求。
3. SQL 执行失败要抛出可读异常。
4. 日志中不要输出密码。
5. 支持执行 CREATE DATABASE、CREATE TABLE、INSERT、SELECT。

## 九、类型映射

新增：

```text
ClickHouseTypeMapper
```

MySQL 到 ClickHouse 类型映射：

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

如果字段 nullable，则使用：

```text
Nullable(Type)
```

要求：

1. 不确定类型统一转 String。
2. 金额类 decimal 优先 Decimal(18, 4)。
3. 时间字段用于后续分区键选择。

## 十、加速服务类

新增目录：

```text
app/Services/Acceleration/
```

如果项目已有 Modules 结构，请按已有规范放置。

建议新增：

```text
AccelerationProfileService
AccelerationSchemaService
AccelerationSyncService
AccelerationQueryRouter
AccelerationEligibilityChecker
ClickHouseSqlGenerator
ClickHouseTypeMapper
ClickHouseClient
```

### 1. AccelerationProfileService

负责：

1. 创建 profile。
2. 初始化字段映射。
3. 启用 / 禁用 profile。
4. 查询 dataset 的 active profile。
5. 更新状态。
6. 更新 version。

### 2. AccelerationSchemaService

负责：

1. 根据 dataset_fields 生成 ClickHouse 字段。
2. 生成安全 target_table 名。
3. 生成 CREATE TABLE SQL。
4. 创建 ClickHouse database。
5. 创建 ClickHouse detail table。
6. 删除或重建表。

ClickHouse 第一版建表策略：

如果 dataset 有日期字段：

```text
MergeTree
PARTITION BY toYYYYMM(date_field)
ORDER BY (date_field)
```

如果没有日期字段：

```text
MergeTree
ORDER BY tuple()
```

### 3. AccelerationSyncService

负责：

1. 创建同步任务。
2. 分批读取源数据。
3. 写入 ClickHouse。
4. 更新任务状态。
5. 更新 profile 状态。
6. 同步成功后清理相关图表缓存。
7. 同步失败时记录错误。

### 4. AccelerationEligibilityChecker

负责判断图表查询能否走加速层。

第一版可走加速的条件：

1. 配置开启 BI_ACCELERATION_ENABLED。
2. dataset 存在 active profile。
3. profile engine_type = clickhouse。
4. profile status = active。
5. 查询字段都能在 acceleration_columns 找到映射。
6. filter 字段都能映射。
7. sort 字段都能映射。
8. 权限 filter 字段都能映射。
9. 查询不包含无法转换的自定义 SQL 表达式。

不可走加速时返回原因。

### 5. AccelerationQueryRouter

负责：

1. 接收当前查询上下文。
2. 调用 EligibilityChecker。
3. 如果可加速，调用 ClickHouseSqlGenerator。
4. 执行 ClickHouse 查询。
5. 失败时根据配置 fallback。
6. 返回查询结果和 acceleration metadata。

返回 metadata 包括：

```text
acceleration_hit
acceleration_profile_id
acceleration_engine
fallback_used
fallback_reason
accelerated_duration_ms
```

### 6. ClickHouseSqlGenerator

负责生成 ClickHouse SQL。

第一版支持：

1. dimensions
2. metrics
3. filters
4. permission_filters
5. group by
6. order by
7. limit

聚合函数支持：

```text
sum
avg
count
countDistinct
min
max
```

时间颗粒支持：

```text
year    -> toYear(field)
month   -> toStartOfMonth(field)
day     -> toDate(field)
hour    -> toStartOfHour(field)
```

要求：

1. 字段名只能来自 acceleration_columns。
2. 操作符必须白名单。
3. 值必须安全处理。
4. 不允许前端传入 ClickHouse 表名。
5. 不允许拼接未校验字段。

## 十一、同步任务 Job

新增 Job：

```text
BuildAccelerationTableJob
```

职责：

1. 根据 acceleration_profile_id 查找 profile。
2. 设置 profile status = building。
3. 创建或重建 ClickHouse 表。
4. 从原始数据源分批读取数据。
5. 写入 ClickHouse。
6. 记录 source_row_count 和 target_row_count。
7. 成功后设置 profile status = active。
8. 失败后设置 profile status = failed。
9. 写入 acceleration_tasks。
10. 清理相关缓存。

要求：

1. 使用队列执行。
2. 不要在 HTTP 请求中直接同步大量数据。
3. 同步过程要有日志。
4. 失败不能影响原始查询。

## 十二、API 设计

新增路由前先检查当前项目路由风格。

建议新增：

```text
GET    /api/acceleration/profiles
POST   /api/acceleration/profiles
GET    /api/acceleration/profiles/{profile}
PUT    /api/acceleration/profiles/{profile}
DELETE /api/acceleration/profiles/{profile}

POST   /api/acceleration/profiles/{profile}/test
POST   /api/acceleration/profiles/{profile}/build
POST   /api/acceleration/profiles/{profile}/activate
POST   /api/acceleration/profiles/{profile}/disable

GET    /api/acceleration/tasks
GET    /api/acceleration/tasks/{task}

GET    /api/datasets/{dataset}/acceleration
POST   /api/datasets/{dataset}/acceleration/build
```

第一版重点接口：

1. 为 dataset 创建 profile。
2. 构建 ClickHouse 加速表。
3. 查看同步任务状态。
4. 查看 dataset 当前加速状态。

## 十三、查询链路接入

在现有图表查询流程中接入。

不要重写整个查询引擎。

推荐流程：

```text
Chart Query API
    ↓
原有 QueryService
    ↓
构建查询上下文 / Query DTO
    ↓
调用 AccelerationQueryRouter
    ↓
如果返回加速结果，直接返回
    ↓
如果不可加速或 fallback，继续走原有查询逻辑
```

要求：

1. 原有查询逻辑必须保留。
2. 加速失败不能导致图表不可用。
3. query_logs 要记录是否走加速。
4. 缓存 key 要区分加速和非加速结果。
5. 权限 filter 必须在加速查询中生效。

## 十四、缓存 key 调整

如果当前已有图表查询缓存，请调整 key。

建议 key：

```text
bi:chart:{chart_id}:user:{user_id}:acc:{profile_id}:v:{version}:query:{query_hash}
```

未走加速时：

```text
bi:chart:{chart_id}:user:{user_id}:raw:query:{query_hash}
```

要求：

1. 加速表重建成功后 version + 1。
2. version 变化后旧缓存自然失效。
3. 图表配置、数据集、权限变化仍按原逻辑失效。

## 十五、权限要求

加速层不能绕过权限。

必须满足：

1. 资源权限仍然先检查。
2. 行级数据权限仍然合并到查询条件。
3. 列级权限仍然过滤返回字段。
4. 权限字段无法映射到 ClickHouse 时，必须 fallback 原始查询。
5. 不允许前端指定是否绕过权限。
6. 不允许前端直接查询 ClickHouse 表。

## 十六、文档

新增：

```text
docs/bi-acceleration-clickhouse.md
```

内容包括：

1. 为什么引入 ClickHouse。
2. 当前加速架构。
3. MySQL、Redis、ClickHouse 的职责边界。
4. acceleration_profiles 表说明。
5. acceleration_columns 表说明。
6. acceleration_tasks 表说明。
7. ClickHouse 建表策略。
8. 数据同步流程。
9. 查询路由流程。
10. fallback 机制。
11. 缓存 key 变化。
12. query_logs 字段变化。
13. 如何启动 ClickHouse。
14. 如何创建加速配置。
15. 如何构建加速表。
16. 当前实现边界。
17. 后续扩展方向：预聚合、物化视图、StarRocks、Doris、CDC。

## 十七、验收标准

完成后需要满足：

1. docker compose 可以启动 ClickHouse，或有独立 ClickHouse compose 文件。
2. Laravel 可以测试 ClickHouse 连接。
3. 可以为 dataset 创建 acceleration_profile。
4. 可以生成 acceleration_columns 字段映射。
5. 可以触发 BuildAccelerationTableJob。
6. 可以在 ClickHouse 创建明细表。
7. 可以把 dataset 数据同步到 ClickHouse。
8. acceleration_tasks 能记录同步状态。
9. profile 同步成功后状态变为 active。
10. 图表查询符合条件时可以走 ClickHouse。
11. 图表查询不符合条件时走原始查询。
12. ClickHouse 查询失败时可以 fallback 原始查询。
13. query_logs 能记录 acceleration_hit、fallback_used、fallback_reason。
14. 缓存 key 能区分加速查询和原始查询。
15. 权限 filter 在加速查询中仍然生效。
16. README 或 docs 有清晰使用说明。
17. 不破坏已有功能。
18. 尽量通过 php artisan test。
19. 尽量通过 php artisan route:list。
20. migration 可以正常执行和回滚。

## 十八、运行命令

尽量运行：

```text
php artisan migrate
php artisan route:list
php artisan test
```

如果有 Docker：

```text
docker compose ps
docker compose up -d clickhouse
```

如果有队列：

```text
php artisan queue:work
```

如果有前端：

```text
cd frontend
npm run build
```

## 十九、最终输出

任务完成后，请输出：

1. 本次完成内容
2. 修改文件列表
3. 新增 migration 列表
4. 新增 Model 列表
5. 新增 Service 列表
6. 新增 Job 列表
7. 新增 API 列表
8. ClickHouse Docker 启动方式
9. 加速表构建流程
10. 查询路由流程
11. fallback 机制说明
12. query_logs 增强字段说明
13. 缓存 key 变化说明
14. 文档位置
15. 运行过的命令
16. 测试结果
17. 当前实现边界
18. 下一阶段建议