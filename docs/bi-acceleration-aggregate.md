# 预聚合表 / 物化视图加速

本文档对应 Phase 10.2。当前实现是在 ClickHouse 明细表加速之上新增预聚合表能力，用于高频图表、固定维度指标组合和仪表盘场景。

## 目标

- 为 dataset 创建预聚合定义。
- 按时间颗粒、维度和指标生成 ClickHouse 聚合表。
- 从已激活的 ClickHouse 明细表执行 `INSERT INTO aggregate SELECT ... FROM detail GROUP BY ...`。
- 查询时优先命中预聚合表，不满足条件时回退明细表，再回退原始数据源。
- 在 `query_logs` 记录 `acceleration_mode`、`aggregate_definition_id`、`aggregate_table` 和 `detail_fallback_used`。

## 元数据表

### acceleration_aggregate_definitions

保存预聚合定义：

- `dataset_id`：所属数据集。
- `detail_profile_id`：来源 ClickHouse 明细表 profile。
- `aggregate_profile_id`：聚合表自身的 `aggregate_table` profile。
- `status`：`disabled`、`building`、`active`、`failed`。
- `target_database` / `target_table`：ClickHouse 聚合表位置。
- `time_field` / `time_grain`：支持 `none`、`day`、`month`、`year`。
- `dimensions_json`：可 group by 的维度字段。
- `metrics_json`：预计算指标，例如 `sum(amount)`、`count(id)`。
- `filters_json`：构建聚合表时固定过滤条件。
- `row_count` / `version`：用于状态展示和缓存失效。

### acceleration_aggregate_columns

保存聚合表字段：

- `column_role = time_grain`：时间颗粒列，例如 `ordered_at_month`。
- `column_role = dimension`：维度列。
- `column_role = metric`：指标列。
- `aggregate_function`：`sum`、`avg`、`count`、`countDistinct`、`min`、`max`、`none`。

### query_logs 扩展

新增字段：

- `aggregate_definition_id`
- `aggregate_table`
- `detail_fallback_used`

聚合命中时：

```text
acceleration_hit = true
acceleration_mode = aggregate_table
aggregate_definition_id = ...
```

聚合失败后命中明细表时：

```text
acceleration_hit = true
acceleration_mode = detail_table
fallback_used = true
detail_fallback_used = true
aggregate_definition_id = ...
```

## 构建流程

```text
创建 aggregate definition
  -> 生成 aggregate profile
  -> 生成 aggregate columns
  -> POST build/refresh
  -> acceleration_tasks: build_aggregate / refresh_aggregate
  -> BuildAggregateTableJob
  -> AggregateBuildService
  -> CREATE DATABASE
  -> DROP/CREATE aggregate table
  -> INSERT INTO aggregate SELECT ... FROM detail GROUP BY ...
  -> count rows
  -> definition/profile active
  -> version + 1
  -> 清理该 dataset 下图表缓存
```

来源明细 profile 必须是 `engine_type = clickhouse`、`mode = detail_table` 且 `status = active`。

## 命中规则

`AggregateEligibilityChecker` 只在以下条件都满足时命中：

- 全局加速和聚合加速开启。
- aggregate definition 和 aggregate profile 均为 `active`。
- detail profile 为 `active`。
- 查询维度是预聚合维度和时间颗粒的子集。
- 查询指标已在 `metrics_json` 中定义。
- filter 和行级权限 filter 使用的字段存在于聚合表维度或时间颗粒列中。
- `avg`、`countDistinct` 仅支持查询粒度与聚合定义完全一致。
- ClickHouse 聚合表存在。

权限字段没有被聚合时不会查询预聚合表，会自动降级到明细表或原始数据源。

## 查询顺序

```text
QueryService
  -> Redis cache
  -> AggregateQueryRouter
      -> hit: AggregateSqlGenerator -> ClickHouseClient
      -> fail/miss: detail route
  -> AccelerationQueryRouter
      -> hit: ClickHouseSqlGenerator -> ClickHouse detail table
      -> fail/miss: source route
  -> original SqlCompiler + QueryExecutor
```

## 缓存 key

聚合命中会使用独立 key 段：

```text
agg:{aggregate_definition_id}:v:{version}
```

明细表仍使用：

```text
acc:hit:profile:{profile_id}:v:{version}
```

原始查询使用：

```text
acc:raw:profile:none:v:0
```

聚合表刷新成功后会清理该 dataset 下图表查询缓存。

## API

```text
GET    /api/acceleration/aggregates
POST   /api/acceleration/aggregates
GET    /api/acceleration/aggregates/{aggregate}
PUT    /api/acceleration/aggregates/{aggregate}
DELETE /api/acceleration/aggregates/{aggregate}
POST   /api/acceleration/aggregates/{aggregate}/build
POST   /api/acceleration/aggregates/{aggregate}/refresh
POST   /api/acceleration/aggregates/{aggregate}/activate
POST   /api/acceleration/aggregates/{aggregate}/disable
GET    /api/datasets/{dataset}/acceleration/aggregates
POST   /api/datasets/{dataset}/acceleration/aggregates
```

创建示例：

```bash
curl -X POST http://localhost:8080/api/datasets/1/acceleration/aggregates \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "detail_profile_id": 1,
    "name": "Monthly Province Sales",
    "time_field": "ordered_at",
    "time_grain": "month",
    "dimensions": ["province"],
    "metrics": [
      {"field": "amount", "aggregate": "sum", "alias": "amount_sum"}
    ]
  }'
```

构建示例：

```bash
curl -X POST http://localhost:8080/api/acceleration/aggregates/1/build \
  -H "Authorization: Bearer <token>"
```

## 当前边界

- 只实现 ClickHouse 聚合表，不创建 ClickHouse materialized view。
- 不做自动推荐、复杂优化器、多层 cube、跨 dataset join。
- `avg` 和 `countDistinct` 不做跨粒度 rollup。
- 固定 filters 只用于构建聚合表；查询 filter 必须命中聚合列。
- 聚合表依赖明细表已构建并激活。

## 后续方向

- 支持 ClickHouse materialized view 自动刷新。
- 为 `avg` 维护 `sum/count` 双指标并支持安全 rollup。
- 增加聚合命中率统计和推荐候选。
- 增加 scheduled refresh。
- 扩展 StarRocks / Doris 聚合表 driver。
