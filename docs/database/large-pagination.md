# 大数据分页优化：Offset 与 Seek Pagination

本文记录指标列表在大数据量下的分页优化思路，对应 `P1-08 MySQL 大数据性能实证`。

## 1. 代码入口

- 普通分页：`app/Domains/Metrics/Queries/MetricQuery.php`
- Seek 示例：`app/Console/Commands/MetricSeekPageCommand.php`
- 命令：`php artisan metrics:seek-page`
- 测试：`tests/Feature/PhaseNineDatabasePerformanceTest.php`

## 2. Offset Pagination

普通分页常见 SQL：

```sql
SELECT id, code, name, status
FROM metrics
WHERE status = 'active'
ORDER BY id DESC
LIMIT 20 OFFSET 100000;
```

问题：

- MySQL 需要跳过前 100000 行，再返回 20 行。
- offset 越大，扫描和丢弃的行越多。
- 排序字段如果不能高效使用索引，还会出现 filesort。

适用：

- 页数较浅。
- 后台管理列表。
- 用户需要跳页。

## 3. Seek Pagination

Seek pagination 使用上一页最后一条记录作为游标：

```sql
SELECT id, code, name, status
FROM metrics
WHERE status = 'active'
  AND id < :last_seen_id
ORDER BY id DESC
LIMIT 20;
```

项目命令：

```bash
php artisan metrics:seek-page --limit=20
php artisan metrics:seek-page --after-id=1000 --limit=20
```

优点：

- 不需要跳过大量历史行。
- 越往后翻，性能更稳定。
- 适合无限滚动、导出游标、后台逐页扫描。

限制：

- 不适合任意跳页。
- 排序字段必须稳定且唯一，常用 `id` 或 `(created_at, id)`。
- 前端需要保存 `next_after_id`。

## 4. Cursor、chunk、lazy

Laravel 常见选择：

| 方法 | 适用场景 | 风险 |
| --- | --- | --- |
| `paginate()` | 后台列表，需要页码和总数 | 大 offset 慢，总数统计也可能慢 |
| `cursorPaginate()` | 游标分页 API | 不适合复杂跳页 |
| `chunkById()` | 批处理历史数据 | 处理过程中要注意新增/删除数据 |
| `cursor()` | 流式读取 | 单条处理慢时连接占用时间长 |
| `LazyCollection` | 文件和大结果集流式处理 | 仍需控制每条处理成本 |

## 5. 当前项目取舍

- 管理后台 `/api/v1/metrics` 当前保留 `paginate()`，因为用户需要页码和筛选。
- 大数据导出、后台扫描和性能演示使用 `metrics:seek-page` 展示 seek pagination。
- 后续如果指标表数据继续增长，可以为 API 增加 `cursorPaginate()` 模式。

## 6. 面试追问

基础问题：

1. 为什么 offset 大了会慢？
2. Seek pagination 的游标是什么？
3. `cursorPaginate()` 和 `paginate()` 有什么区别？
4. `chunk()` 和 `chunkById()` 有什么区别？
5. 为什么排序字段必须稳定？

资深追问：

1. 用 `created_at` 做 seek pagination 有什么坑？
2. 如何支持复合排序的 seek pagination？
3. 大数据导出为什么不能直接 `get()`？
4. 统计总数很慢时产品上如何取舍？
5. 分库分表后 seek pagination 如何设计？

## 7. 验收命令

```bash
php artisan metrics:seed-large-dataset --rows=100000 --metrics=100 --batch=1000
php artisan metrics:seek-page --limit=20
php artisan metrics:seek-page --after-id=1000 --limit=20
php artisan test --filter=PhaseNineDatabasePerformanceTest
```
