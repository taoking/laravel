# ClickHouse 查询加速最小闭环

本文档对应 Phase 10.1，说明当前 Laravel BI Platform 的 ClickHouse 明细表加速闭环。目标是把符合条件的图表/查询自动路由到 ClickHouse；不符合条件或 ClickHouse 失败时回退原始数据源。

## 为什么引入 ClickHouse

BI 查询通常包含大表扫描、维度筛选、分组聚合和排序。MySQL 继续承担系统元数据和小规模事务型数据存储；ClickHouse 作为列式分析库，适合承载导入表或指定 dataset 的明细宽表，加速图表聚合查询。

## 当前架构

```text
Chart / Query API
  -> QueryService
  -> QueryRequestDTO + Dataset metadata
  -> AccelerationQueryRouter
  -> AccelerationEligibilityChecker
     -> hit: ClickHouseSqlGenerator -> ClickHouseClient
     -> miss: original SqlCompiler -> QueryExecutor
  -> QueryLogService
  -> QueryCacheService
```

职责边界：

- MySQL：用户、权限、数据源、数据集、图表、仪表盘、导入导出、查询日志、加速元数据。
- Redis/Cache：查询结果缓存、图表查询缓存索引。
- ClickHouse：由 `acceleration_profiles` 管理的明细加速表。
- Queue：`BuildAccelerationTableJob` 异步构建或刷新加速表。

## 表结构

### acceleration_profiles

保存 dataset 的 ClickHouse 加速配置。

关键字段：

- `dataset_id`：关联 `datasets`。
- `engine_type`：第一版使用 `clickhouse`。
- `mode`：第一版使用 `detail_table`。
- `status`：`disabled`、`building`、`active`、`failed`。
- `target_database` / `target_table`：ClickHouse 明细表位置。
- `refresh_type`：第一版使用 `manual`。
- `row_count`：最近成功同步的目标行数。
- `version`：加速表版本，用于缓存隔离。
- `config_json`：预留分区键、排序键等策略。

### acceleration_columns

保存 dataset 字段到 ClickHouse 字段的安全映射。

关键字段：

- `dataset_field_id`
- `source_field_name`
- `target_field_name`
- `source_type`
- `target_type`
- `is_dimension`
- `is_metric`
- `is_partition_key`
- `is_order_key`
- `is_nullable`

ClickHouse SQL 只会使用这里的 `target_field_name`，不会接受前端传入物理表字段名。

### acceleration_tasks

记录同步任务状态。

关键字段：

- `task_type`：`full_sync` / `rebuild` 预留。
- `status`：`pending`、`running`、`success`、`failed`。
- `source_row_count`
- `target_row_count`
- `duration_ms`
- `error_message`
- `logs_json`
- `created_by`

### query_logs 增强字段

- `acceleration_hit`
- `acceleration_profile_id`
- `acceleration_engine`
- `fallback_used`
- `fallback_reason`
- `accelerated_duration_ms`
- `source_duration_ms`
- `acceleration_mode`

未走加速时 `acceleration_hit = false`；ClickHouse 失败回退时 `fallback_used = true`。

## ClickHouse 建表策略

字段类型由 `ClickHouseTypeMapper` 生成：

```text
integer / bigint -> Int64
decimal          -> Decimal(18, 4)
float / double   -> Float64
string / json    -> String
date             -> Date
datetime         -> DateTime
tinyint          -> Int8
boolean          -> UInt8
```

nullable 字段会包成 `Nullable(Type)`。

建表使用 `MergeTree`：

- 有日期/时间字段时，优先作为 `PARTITION BY toYYYYMM(field)`。
- 排序键使用日期字段和前几个维度字段。
- 没有可用排序键时使用 `ORDER BY tuple()`。

## 数据同步流程

1. 创建或选择 `acceleration_profile`。
2. 初始化 `acceleration_columns` 字段映射。
3. 调用构建接口创建 `acceleration_task`。
4. 分发 `BuildAccelerationTableJob`。
5. Job 将 profile 置为 `building`。
6. ClickHouse 中重建明细表。
7. 从 dataset 原始数据源按 chunk 分批读取。
8. 使用 ClickHouse `JSONEachRow` 写入。
9. 成功后 task 置为 `success`，profile 置为 `active`，`version + 1`。
10. 失败后 task/profile 置为 `failed` 并记录 `error_message`。
11. 成功后清理同 dataset 图表查询缓存。

## 查询路由流程

1. 原有资源权限和列权限先校验。
2. 原始 MySQL SQL 仍先编译，作为 fallback 路径。
3. `AccelerationQueryRouter` 构建逻辑查询上下文。
4. `AccelerationEligibilityChecker` 检查：
   - 加速配置已开启。
   - dataset 存在 active profile。
   - engine 为 `clickhouse`。
   - mode 为 `detail_table`。
   - dimensions / metrics / filters / permission filters 都有字段映射。
   - ClickHouse 目标表存在。
5. 可加速时生成 ClickHouse SQL 并执行。
6. 不可加速时继续原始查询。
7. ClickHouse 查询失败时按配置 fallback 原始查询。

## fallback 机制

默认：

```php
'query' => [
    'fallback_on_error' => true,
]
```

fallback 场景：

- 没有 active profile。
- 字段映射缺失。
- 权限 filter 字段无法映射。
- ClickHouse 连接失败。
- 目标表不存在。
- ClickHouse SQL 生成或执行失败。

fallback 会写入 `query_logs.fallback_reason`。

## 缓存 key 变化

图表查询缓存区分 raw 和 accelerated：

```text
bi:chart:{chart_id}:query:{scope}:acc:hit:profile:{profile_id}:v:{version}:{query_hash}
bi:chart:{chart_id}:query:{scope}:acc:raw:profile:none:v:0:{query_hash}
```

普通查询：

```text
bi:query:{scope}:acc:{hit|raw}:profile:{profile_id|none}:v:{version}:{query_hash}
```

加速表重建成功后 `version + 1`，旧缓存自然失效。

## 启动 ClickHouse

`.env`：

```env
BI_ACCELERATION_ENABLED=true
BI_ACCELERATION_ENGINE=clickhouse
BI_CLICKHOUSE_HOST=clickhouse
BI_CLICKHOUSE_PORT=8123
BI_CLICKHOUSE_DATABASE=bi_accelerator
BI_CLICKHOUSE_USERNAME=default
BI_CLICKHOUSE_PASSWORD=
```

启动：

```bash
docker compose up -d clickhouse
docker compose up -d queue-worker
docker compose exec php-fpm php artisan migrate
```

## 创建加速配置

预览字段映射：

```bash
curl -H "Authorization: Bearer <token>" \
  http://localhost:8080/api/datasets/1/acceleration/columns
```

创建 profile：

```bash
curl -X POST http://localhost:8080/api/acceleration/profiles \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"dataset_id":1,"name":"Orders ClickHouse","engine_type":"clickhouse","mode":"detail_table"}'
```

构建加速表：

```bash
curl -X POST http://localhost:8080/api/acceleration/profiles/1/build \
  -H "Authorization: Bearer <token>"
```

也可以直接从 dataset 入口创建并构建：

```bash
curl -X POST http://localhost:8080/api/datasets/1/acceleration/build \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"engine_type":"clickhouse","mode":"detail_table"}'
```

## API 列表

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
GET    /api/datasets/{dataset}/acceleration/columns
```

## 当前实现边界

- 只做 ClickHouse 明细表加速。
- 只做全量同步。
- 不做 Kafka / Debezium / Flink。
- 不做实时 CDC。
- 不做多节点 ClickHouse 集群。
- 不做复杂 SQL 优化器。
- 不做自动物化视图推荐。
- 不重写现有查询引擎、权限系统、导入导出流程。

## 后续扩展方向

- 增量同步。
- 预聚合表和物化视图。
- 基于 query_logs 的慢查询和高频图表推荐。
- StarRocks / Doris Driver。
- ClickHouse 查询耗时、失败率、磁盘、内存监控。
