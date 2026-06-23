# BI 查询加速方案

本文档说明当前项目的查询加速层实现。该能力是对现有查询引擎的可选增强，不替代 MySQL 元数据存储，也不绕过现有数据权限、查询缓存和查询日志流程。

## 总体架构

```text
Chart / Query API
  -> QueryService
  -> LogicalQueryPlan
  -> AggregateQueryRouter
     -> hit: ClickHouse aggregate SQL -> ClickHouse HTTP
     -> miss/fallback: AccelerationQueryRouter
  -> AccelerationQueryRouter
     -> hit: ClickHouse detail SQL -> ClickHouse HTTP
     -> miss/fallback: 原始 MySQL SQL -> DataSourceConnectionFactory
  -> QueryLogService
  -> QueryCacheService
  -> QueryLogAnalysisService / Recommendation / Refresh / Benefit Report
```

职责边界：

- MySQL：系统元数据、用户权限、数据源、数据集、图表、仪表盘、导入任务、查询日志。
- Redis/Cache：查询结果缓存、图表缓存索引、后续任务状态扩展。
- ClickHouse：查询加速明细表、预聚合表，后续可扩展物化视图。
- Laravel Queue：加速表构建和刷新任务。
- Laravel Scheduler：扫描刷新计划并派发刷新任务。

## 为什么需要列式数据库

BI 图表通常会对大量明细数据做维度筛选、分组和指标聚合。MySQL 适合事务和系统元数据，但在大宽表、多维聚合、高频仪表盘场景下容易出现慢查询。ClickHouse 等列式数据库按列存储并针对聚合扫描优化，适合作为 BI 查询加速层。

## 数据表

### acceleration_profiles

描述一个数据集的加速配置。

关键字段：

- `dataset_id`：所属数据集。
- `engine_type`：第一版支持 `clickhouse`，预留 `starrocks`、`doris`、`mysql_summary`。
- `mode`：已落地 `detail_table` 和 `aggregate_table`，预留 `materialized_view`。
- `status`：`disabled`、`building`、`active`、`failed`。
- `target_database`、`target_table`：加速表目标位置。
- `row_count`：最近成功同步的目标行数。
- `version`：用于缓存 key 失效。
- `config_json`：保存分区、排序、引擎特定参数。

### acceleration_columns

记录数据集字段到加速表字段的映射。

关键字段：

- `source_field_name`：数据集字段名。
- `target_field_name`：ClickHouse 字段名。
- `target_type`：ClickHouse 类型，例如 `Nullable(String)`、`Nullable(Decimal(18, 4))`。
- `is_dimension`、`is_metric`：字段用途。
- `is_partition_key`、`is_order_key`：建表策略。

### acceleration_tasks

记录构建和刷新任务。

关键字段：

- `task_type`：`full_sync`、`build_aggregate`、`refresh_aggregate`，后续可扩展 `incremental_sync`、`refresh_mv`、`rebuild`。
- `status`：`pending`、`running`、`success`、`failed`。
- `source_row_count`、`target_row_count`、`duration_ms`。
- `error_message`：失败原因。

### query_logs 增强字段

- `acceleration_hit`：是否走加速层成功。
- `acceleration_profile_id`：命中的 profile。
- `acceleration_engine`：加速引擎。
- `acceleration_mode`：加速模式。
- `fallback_used`：是否从加速层回退原始查询。
- `fallback_reason`：未命中或回退原因。
- `source_duration_ms`：原始查询耗时，用于可选对比。
- `accelerated_duration_ms`：加速查询耗时。
- `aggregate_definition_id`：聚合命中或聚合失败降级时关联的预聚合定义。
- `aggregate_table`：预聚合目标表。
- `detail_fallback_used`：预聚合失败后是否降级到明细表。

### acceleration_aggregate_definitions / acceleration_aggregate_columns

预聚合表元数据，详见 [预聚合表 / 物化视图加速](bi-acceleration-aggregate.md)。

### acceleration_recommendations / acceleration_refresh_schedules / acceleration_benefit_reports

查询日志推荐、刷新计划和收益统计元数据，详见 [查询日志推荐、自动刷新和收益统计](bi-acceleration-recommendation.md)。

## 加速表构建流程

1. 调用 API 创建 `acceleration_profile`，或直接调用数据集构建接口。
2. 系统根据 `dataset_fields` 生成默认字段映射。
3. 创建 `acceleration_task`，profile 状态改为 `building`。
4. `ProcessAccelerationTaskJob` 进入队列。
5. Job 删除旧 ClickHouse 表并重建明细表。
6. 从数据集原始表按 `BI_ACCELERATION.sync.chunk_size` 分批读取。
7. 使用 `JSONEachRow` 写入 ClickHouse。
8. 成功后 profile 变为 `active`，`version + 1`，记录行数和成功时间。
9. 清理同数据集下图表查询缓存。
10. 失败时 profile 和 task 标记 `failed`，写入错误信息。

## 查询路由流程

1. `QueryService` 仍然先做资源权限、字段权限和 QueryRequest 校验。
2. 编译原始 MySQL SQL，作为 fallback 路径。
3. 构建 `LogicalQueryPlan`，合并行级权限 filter。
4. `AggregateQueryRouter` 优先查找 active 预聚合定义。
5. 聚合命中时生成 ClickHouse 聚合表 SQL 并查询。
6. 聚合未命中或执行失败时，进入 `AccelerationQueryRouter` 查找 active 明细 profile。
7. 明细层检查字段、filter 和权限字段是否都有 `acceleration_columns` 映射。
8. 检查 Driver 是否支持当前 plan 和目标表是否存在。
9. 明细命中后生成 ClickHouse SQL 并查询。
10. 明细未命中或失败时，根据配置回退原始查询。

## fallback 机制

默认配置：

```php
'query' => [
    'fallback_on_error' => true,
]
```

会回退的场景：

- 没有 active profile。
- 字段映射缺失。
- ClickHouse 连接失败。
- 聚合表或明细表不存在。
- SQL 生成或执行失败。

回退查询会写入 `query_logs.fallback_used = true`，并在 `fallback_reason` 中记录原因。

## 权限如何生效

加速查询不会绕过权限：

- 资源权限仍由 `DataPermissionService::assertCanAccessDataset` 先校验。
- 列级隐藏字段仍由 `QueryRequestValidator` 拦截。
- 行级规则会转换为 `LogicalQueryPlan.permissionFilters`。
- 预聚合命中时，权限字段必须存在聚合表维度或时间颗粒列；不能覆盖时降级到明细表或原始库。
- 明细加速命中时，权限字段必须存在加速字段映射；不能映射时不走加速层。
- 前端不能传 ClickHouse 表名，目标表来自 profile 元数据。

## 缓存 key 设计

查询缓存 key 增加加速状态、profile 和 version：

```text
bi:chart:{chart_id}:query:{scope}:acc:{hit|raw}:profile:{profile_id|none}:v:{version}:{query_hash}
bi:query:{scope}:acc:{hit|raw}:profile:{profile_id|none}:v:{version}:{query_hash}
```

预聚合命中时使用独立段：

```text
bi:chart:{chart_id}:query:{scope}:agg:{aggregate_definition_id}:v:{version}:{query_hash}
bi:query:{scope}:agg:{aggregate_definition_id}:v:{version}:{query_hash}
```

profile 状态变化、加速表构建成功、字段映射变化都会递增 `version` 或清理图表缓存，从而避免原始查询和加速查询结果混用。

## API

Profile 管理：

```text
GET    /api/acceleration/profiles
POST   /api/acceleration/profiles
GET    /api/acceleration/profiles/{profile}
PUT    /api/acceleration/profiles/{profile}
DELETE /api/acceleration/profiles/{profile}
POST   /api/acceleration/profiles/{profile}/test
POST   /api/acceleration/profiles/{profile}/build
POST   /api/acceleration/profiles/{profile}/refresh
POST   /api/acceleration/profiles/{profile}/disable
POST   /api/acceleration/profiles/{profile}/activate
```

预聚合管理：

```text
GET    /api/acceleration/aggregates
POST   /api/acceleration/aggregates
GET    /api/acceleration/aggregates/{aggregate}
PUT    /api/acceleration/aggregates/{aggregate}
DELETE /api/acceleration/aggregates/{aggregate}
POST   /api/acceleration/aggregates/{aggregate}/build
POST   /api/acceleration/aggregates/{aggregate}/refresh
POST   /api/acceleration/aggregates/{aggregate}/disable
POST   /api/acceleration/aggregates/{aggregate}/activate
```

任务和数据集入口：

```text
GET  /api/acceleration/tasks
GET  /api/acceleration/tasks/{task}
GET  /api/datasets/{dataset}/acceleration
POST /api/datasets/{dataset}/acceleration/build
GET  /api/datasets/{dataset}/acceleration/columns
GET  /api/datasets/{dataset}/acceleration/aggregates
POST /api/datasets/{dataset}/acceleration/aggregates
```

推荐、刷新计划和收益：

```text
GET  /api/acceleration/recommendations
POST /api/acceleration/recommendations/generate
POST /api/acceleration/recommendations/{recommendation}/accept
POST /api/acceleration/recommendations/{recommendation}/reject
GET  /api/acceleration/refresh-schedules
POST /api/acceleration/refresh-schedules
POST /api/acceleration/refresh-schedules/{schedule}/run-now
GET  /api/acceleration/benefit-report
```

## Docker 启动 ClickHouse

`.env` 中保留默认配置即可：

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
php artisan schedule:run
```

完整环境：

```bash
docker compose up -d --build
docker compose exec php-fpm php artisan migrate
```

## 示例流程

1. 预览字段映射：

```bash
curl -H "Authorization: Bearer <token>" \
  http://localhost:8080/api/datasets/1/acceleration/columns
```

2. 创建并构建数据集加速表：

```bash
curl -X POST http://localhost:8080/api/datasets/1/acceleration/build \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"engine_type":"clickhouse","mode":"detail_table"}'
```

3. 可选创建并构建预聚合表。固定维度指标查询会优先走预聚合表。
4. 查询图表或执行查询。符合条件时自动走预聚合表或 ClickHouse 明细表；失败时回退原始数据源。

## 当前实现边界

- 已落地 ClickHouse 明细表和最小可用预聚合表。
- 同步方式是全量同步，不包含 CDC、Kafka、Debezium、Flink。
- 不做跨数据源 Join 自动改写。
- 不自动推荐物化视图。
- `materialized_view` 只保留元数据模式，当前预聚合实现使用物理聚合表。
- ClickHouse SQL 支持常见维度、指标、filter、group by、order by、limit、offset 和时间颗粒。

## 后续扩展

- 增加 StarRocks / Doris Driver。
- 按 query_logs 自动推荐预聚合表。
- 增量同步和导入完成后自动刷新。
- ClickHouse 查询失败率、慢查询、磁盘和内存监控。
- 高频仪表盘物化视图。
- 对强一致查询增加显式禁用加速参数。

## 生产建议

- ClickHouse 使用独立集群，不和 Laravel 应用混部。
- MySQL 继续只存系统元数据和导入小表。
- 大数据导入或同步必须走队列。
- 高频固定图表优先使用预聚合表。
- 实时性要求高的数据使用增量同步或 CDC。
- 一致性要求高的查询允许回退原始库。
- 监控 ClickHouse 查询耗时、失败率、磁盘、内存。
- 根据 query_logs 选择物化视图和汇总表。
- 不要把所有数据集都无脑同步到分析库。
