# 指标查询 Explain 与索引优化实证

本文对应 `P1-08 MySQL 大数据性能实证`，用于把指标查询优化从概念说明推进到可运行命令和可复盘证据。

## 1. 查询入口

- API：`GET /api/v1/metrics`
- Query Object：`app/Domains/Metrics/Queries/MetricQuery.php`
- Explain 命令：`php artisan metrics:explain-query`
- 造数命令：`php artisan metrics:seed-large-dataset`
- Seek 分页命令：`php artisan metrics:seek-page`
- 测试：`tests/Feature/PhaseNineDatabasePerformanceTest.php`

## 2. 高频筛选条件

- `metrics.metric_category_id`
- `metrics.status`
- `metrics.name`
- `metrics.code`
- `metric_values.metric_id`
- `metric_values.region_id`
- `metric_values.frequency_id`
- `metric_values.period_date`

## 3. 当前索引

| 表 | 索引 | 用途 |
| --- | --- | --- |
| `metrics` | `unique(code)` | 指标编码唯一定位 |
| `metrics` | `(metric_category_id, status)` | 分类和状态筛选 |
| `metrics` | `(name, code)` | 名称和编码查询辅助 |
| `metric_values` | unique `(metric_id, region_id, frequency_id, period_date)` | 导入幂等和按指标周期定位 |
| `metric_values` | `(region_id, frequency_id, period_date)` | 按地区、频率、时间范围筛选 |
| `regions` | `unique(code)` | 地区编码过滤 |
| `frequencies` | `unique(code)` | 频率编码过滤 |

## 4. 造数命令

小数据验证：

```bash
php artisan metrics:seed-large-dataset --rows=1000 --metrics=20 --batch=200
```

面试/压测演示：

```bash
php artisan metrics:seed-large-dataset --rows=100000 --metrics=100 --batch=1000
php artisan metrics:seed-large-dataset --rows=1000000 --metrics=500 --batch=2000
```

dry-run：

```bash
php artisan metrics:seed-large-dataset --rows=100000 --metrics=100 --batch=1000 --dry-run
```

设计说明：

- 使用 `MetricValue::upsert()`，避免重复造数产生重复周期数据。
- 行分布到多个指标，避免单指标热点影响查询判断。
- 默认 source 为 `large-dataset-lab`，便于统计和清理。

## 5. Explain 命令

```bash
php artisan metrics:explain-query \
  --region-code=PERF-CN \
  --frequency-code=perf_daily \
  --date-from=2024-01-01 \
  --limit=20
```

命令会根据数据库 driver 自动选择：

- SQLite：`EXPLAIN QUERY PLAN`
- MySQL：`EXPLAIN`

核心 SQL：

```sql
SELECT m.id, m.code, m.name
FROM metrics m
WHERE m.status = 'active'
  AND EXISTS (
    SELECT 1
    FROM metric_values mv
    INNER JOIN regions r ON r.id = mv.region_id
    INNER JOIN frequencies f ON f.id = mv.frequency_id
    WHERE mv.metric_id = m.id
      AND r.code = 'PERF-CN'
      AND f.code = 'perf_daily'
      AND mv.period_date >= '2024-01-01'
  )
ORDER BY m.id DESC
LIMIT 20;
```

## 6. Explain 关注点

MySQL 中重点看：

- `type`：是否从 `ALL` 下降到 `range`、`ref`、`eq_ref`。
- `key`：是否命中预期索引。
- `rows`：扫描行数是否随条件收敛。
- `Extra`：是否出现 `Using filesort`、`Using temporary`。

SQLite 中重点看：

- 是否出现 `SCAN`。
- 是否出现 `SEARCH ... USING INDEX`。
- 子查询是否利用 `metric_values` 索引。

## 7. 索引失效案例

容易失效：

```sql
WHERE DATE(period_date) >= '2024-01-01'
```

更推荐：

```sql
WHERE period_date >= '2024-01-01'
```

原因：对列使用函数会让 B+Tree 中的原始有序值难以直接利用，MySQL 可能无法走范围索引。

## 8. 回表与覆盖索引

当前列表查询最终需要输出 `id`、`code`、`name`、`status`、分类和最新值，因此很难完全依赖一个覆盖索引。

面试表达：

- 如果只查询 `id` 和筛选列，联合索引可能覆盖查询。
- 如果还需要 `name`、`description` 等非索引列，就可能回表。
- 是否回表要结合 Explain 的 `Extra` 和实际索引设计判断。

## 9. 面试问题

基础问题：

1. 当前指标查询有哪些索引？
2. Explain 中 `type`、`key`、`rows` 分别看什么？
3. 为什么排序字段要白名单？
4. `with()` 如何减少 N+1？
5. `whereHas()` 会带来什么 SQL 风险？

资深追问：

1. `where function(column)` 为什么可能导致索引失效？
2. 覆盖索引和回表如何判断？
3. `EXISTS` 和 `JOIN` 在当前查询里如何取舍？
4. 大 offset 分页为什么越来越慢？
5. 如果 `metric_values` 到千万级，索引和归档怎么设计？

## 10. 验收命令

```bash
php artisan test --filter=PhaseNineDatabasePerformanceTest
php artisan metrics:seed-large-dataset --rows=1000 --metrics=20 --batch=200
php artisan metrics:explain-query --region-code=PERF-CN --frequency-code=perf_daily --date-from=2024-01-01 --limit=20
php artisan metrics:seek-page --limit=20
```
