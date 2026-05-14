# 指标查询 Explain 示例

本文记录 Phase 3 指标查询 API 的索引设计和 Explain 复盘入口。

## 查询入口

- API：`GET /api/v1/metrics`
- Query Object：`app/Domains/Metrics/Queries/MetricQuery.php`
- 主要表：`metrics`、`metric_categories`、`metric_values`、`regions`、`frequencies`

## 高频筛选条件

- `metrics.metric_category_id`
- `metrics.status`
- `metrics.name`
- `metrics.code`
- `metric_values.region_id`
- `metric_values.frequency_id`
- `metric_values.period_date`

## 已建立索引

- `metrics(metric_category_id, status)`
- `metrics(name, code)`
- `metric_values(metric_id, region_id, frequency_id, period_date)` 唯一约束
- `metric_values(region_id, frequency_id, period_date)`
- `regions(parent_id, sort_order)`
- `frequencies(code)` 唯一索引

## Explain 复盘命令

MySQL 示例：

```sql
EXPLAIN
SELECT m.*
FROM metrics m
WHERE m.metric_category_id = 1
  AND m.status = 'active'
  AND EXISTS (
    SELECT 1
    FROM metric_values mv
    WHERE mv.metric_id = m.id
      AND mv.region_id = 2
      AND mv.period_date >= '2026-01-01'
  )
ORDER BY m.created_at DESC
LIMIT 20;
```

面试表达重点：

- 指标主表按 `metric_category_id + status` 收敛候选集。
- 指标值表按 `region_id + frequency_id + period_date` 支撑维度和时间范围筛选。
- 详情和列表通过 Eager Loading 预加载 `category`、`latestValue.region`、`latestValue.frequency`，避免列表页逐行查询维度。
- 排序字段使用白名单，避免用户输入直接进入 `order by`。
