# 查询日志推荐、自动刷新和收益统计

本文档对应 Phase 10.3。当前实现基于已有 `query_logs`、ClickHouse 明细表加速和预聚合表加速，增加规则化推荐、手动接受建议、刷新计划和收益报表。

## 为什么需要加速推荐

BI 系统的慢查询和高频图表通常集中在少数 dataset、chart、维度和指标组合上。人工逐个分析 `query_logs` 成本高，系统可以先做规则化扫描，给出可解释建议，再由管理员确认是否创建预聚合定义或刷新计划。

## query_logs 如何用于分析

分析来源：

- `dataset_id`、`chart_id`、`dashboard_id`
- `elapsed_ms`
- `cached`
- `acceleration_hit`
- `acceleration_mode`
- `fallback_used`
- `fallback_reason`
- `aggregate_definition_id`

聚合推荐的维度和指标草案优先从 `charts.config_json` 中读取。没有 chart 配置的日志不会自动生成聚合表定义，避免从任意 SQL 中误推物理结构。

## 推荐规则

### 慢查询推荐预聚合

满足以下条件时，为图表生成 `aggregate_table` 建议：

```text
query_count >= BI_ACCELERATION_RECOMMEND_MIN_QUERY_COUNT
elapsed_ms >= BI_ACCELERATION_RECOMMEND_SLOW_MS
acceleration_mode != aggregate_table
chart config 能解析出 metrics
```

### 高频图表推荐预聚合

满足以下条件时，为图表生成 `aggregate_table` 建议：

```text
query_count >= BI_ACCELERATION_RECOMMEND_HIGH_FREQUENCY
chart config 能解析出稳定 dimensions / metrics
```

### fallback 原因分析

如果某个 dataset 或 chart 频繁 fallback：

```text
fallback_count >= BI_ACCELERATION_RECOMMEND_MIN_QUERY_COUNT
```

系统生成 `detail_table` 类型建议，reason 中保留主要 fallback reason。第一版不自动修复字段映射或权限字段缺失，只提示管理员处理。

### raw 查询推荐明细表

如果 dataset 仍大量走原始库，并且没有 active detail profile：

```text
raw_query_count >= 20
avg_duration_ms >= 2000
```

系统生成 `detail_table` 类型建议。

## 去重和覆盖判断

生成建议前会检查：

- 已有 `pending` / `accepted` / `created` 的等价建议，不重复创建。
- 已有 active aggregate definition 覆盖同一 dataset、dimensions、metrics、time_field、time_grain，不再创建建议。

去重维度：

```text
dataset_id
recommendation_type
dimensions_json
metrics_json
time_field
time_grain
```

## 表说明

### acceleration_recommendations

保存推荐结果：

- `recommendation_type`：`aggregate_table`、`detail_table`、预留 `index`、`cache`。
- `status`：`pending`、`accepted`、`rejected`、`expired`、`created`。
- `priority`：`low`、`medium`、`high`。
- `dimensions_json`、`metrics_json`、`filters_json`：推荐草案。
- `time_field`、`time_grain`：时间颗粒。
- `estimated_*`：查询次数、平均耗时、最大耗时、总耗时和收益分数。
- `source_query_log_ids_json`：最多保留 50 条来源日志 ID。
- `created_profile_id`、`created_aggregate_definition_id`：接受建议后的产物。

### acceleration_refresh_schedules

保存刷新计划：

- `target_type`：`detail_profile` 或 `aggregate_definition`。
- `target_id`：目标对象 ID。
- `refresh_type`：`manual`、`hourly`、`daily`、`weekly`、预留 `cron`。
- `enabled`：是否启用。
- `last_run_at`、`next_run_at`
- `last_task_id`、`last_status`、`last_error_message`

### acceleration_benefit_reports

保存收益快照：

- `query_count`
- `raw_query_count`
- `detail_hit_count`
- `aggregate_hit_count`
- `cache_hit_count`
- `fallback_count`
- `avg_raw_duration_ms`
- `avg_detail_duration_ms`
- `avg_aggregate_duration_ms`
- `avg_cache_duration_ms`
- `estimated_saved_ms`

## 接受推荐流程

管理员接受 `aggregate_table` 建议：

```text
recommendation.status = accepted
  -> 重新校验 dataset fields
  -> 查找等价 aggregate definition
  -> 没有则创建 aggregate definition 和 aggregate profile
  -> recommendation.status = created
  -> 写入 created_aggregate_definition_id / created_profile_id
  -> 默认派发 build_aggregate 任务
```

接受建议不会同步等待 ClickHouse 构建完成。构建失败只影响任务状态，不影响图表原始查询和 fallback。

## 定时刷新流程

```text
Laravel Scheduler
  -> bi:acceleration:refresh-due
  -> 查询 due refresh schedules
  -> detail_profile: 创建 full_sync task 并派发 BuildAccelerationTableJob
  -> aggregate_definition: 创建 refresh_aggregate task 并派发 BuildAggregateTableJob
  -> 更新 last_run_at / next_run_at / last_task_id / last_status
```

Scheduler 只派发任务，不直接执行 ClickHouse 同步。

## 收益统计逻辑

收益报表实时聚合 `query_logs`，也可以通过命令落库成快照。

估算节省时间：

```text
estimated_saved_ms =
  avg_raw_duration_ms * accelerated_query_count
  - actual_accelerated_total_duration_ms
```

如果没有 raw 查询样本，`estimated_saved_ms` 返回 `null`。

## API

推荐：

```text
GET  /api/acceleration/recommendations
POST /api/acceleration/recommendations/generate
GET  /api/acceleration/recommendations/{recommendation}
POST /api/acceleration/recommendations/{recommendation}/accept
POST /api/acceleration/recommendations/{recommendation}/reject
```

刷新计划：

```text
GET    /api/acceleration/refresh-schedules
POST   /api/acceleration/refresh-schedules
GET    /api/acceleration/refresh-schedules/{schedule}
PUT    /api/acceleration/refresh-schedules/{schedule}
DELETE /api/acceleration/refresh-schedules/{schedule}
POST   /api/acceleration/refresh-schedules/{schedule}/enable
POST   /api/acceleration/refresh-schedules/{schedule}/disable
POST   /api/acceleration/refresh-schedules/{schedule}/run-now
```

收益：

```text
GET /api/acceleration/benefit-report
GET /api/acceleration/benefit-report/datasets/{dataset}
GET /api/acceleration/benefit-report/charts/{chart}
```

## Artisan 命令

```bash
php artisan bi:acceleration:recommend --days=7
php artisan bi:acceleration:recommend --days=7 --dry-run
php artisan bi:acceleration:refresh-due
php artisan bi:acceleration:refresh-due --dry-run
php artisan bi:acceleration:benefit-report --days=1
```

## 配置

```env
BI_ACCELERATION_RECOMMENDATION_ENABLED=true
BI_ACCELERATION_ANALYSIS_DAYS=7
BI_ACCELERATION_RECOMMEND_SLOW_MS=3000
BI_ACCELERATION_RECOMMEND_MIN_QUERY_COUNT=5
BI_ACCELERATION_RECOMMEND_HIGH_FREQUENCY=30
BI_ACCELERATION_RECOMMEND_MAX_DIMENSIONS=5
BI_ACCELERATION_RECOMMEND_MAX_METRICS=10
BI_ACCELERATION_REFRESH_ENABLED=true
BI_ACCELERATION_REFRESH_INTERVAL_MINUTES=5
```

## 权限

推荐、接受、拒绝、刷新计划和收益报表接口要求当前用户满足以下任一条件：

- 拥有 `admin` 角色。
- 拥有 `acceleration.manage` 权限。
- 拥有 `datasets.manage` 权限。

命令行任务通常由服务器调度执行，不依赖用户上下文。

## 当前限制

- 不做机器学习推荐。
- 不从任意 SQL 自动反推聚合表结构。
- 不自动创建物理表；只有接受建议后才创建 aggregate definition。
- `detail_table` 建议只提示，不自动创建明细表。
- `cron_expression` 预留，第一版只实现 `hourly`、`daily`、`weekly`。
- 刷新计划只记录任务派发结果，队列任务最终成功/失败仍以 `acceleration_tasks` 为准。

## 生产建议

- 生产环境运行 queue worker 和 scheduler。
- detail profile 和 aggregate definition 的刷新频率要和数据导入频率匹配。
- 大表建议后续接入 CDC、增量同步或 ClickHouse materialized view。
- 对高频仪表盘优先使用预聚合表。
- 监控 query_logs、acceleration_tasks、ClickHouse 查询失败率和磁盘使用。
