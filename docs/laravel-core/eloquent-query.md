# Laravel Eloquent 查询源码追问

## 项目入口

- `app/Domains/Metrics/Queries/MetricQuery.php`
- `app/Http/Controllers/Api/V1/Metrics/MetricController.php`
- `app/Domains/Metrics/Models/Metric.php`
- `app/Domains/Metrics/Models/MetricValue.php`
- `docs/database/metric-query-explain.md`

## 源码类

- `Illuminate\Database\Eloquent\Model`
- `Illuminate\Database\Eloquent\Builder`
- `Illuminate\Database\Query\Builder`
- `Illuminate\Database\Eloquent\Relations\BelongsTo`
- `Illuminate\Database\Eloquent\Relations\HasOne`
- `Illuminate\Database\Eloquent\Relations\HasMany`

## 执行链

以 `MetricQuery::paginate()` 为例：

```text
Metric::query()
  -> with(['category', 'latestValue.region', 'latestValue.frequency'])
  -> when(keyword)
  -> when(status)
  -> whereHas(values)
  -> orderBy(白名单字段)
  -> paginate()
  -> 查询数据库
  -> Hydrate 为 Metric 模型
  -> Eager Load 关联
  -> MetricResource 输出 JSON
```

## 当前项目设计点

- 使用 Query Object 收敛筛选、排序、分页逻辑。
- 排序字段用白名单，防止用户输入任意字段。
- `with()` 提前加载分类和最新指标值，减少 N+1。
- `whereHas()` 用于按关联维度筛选。
- `MetricResource` 控制 API 输出结构。

## with、load、loadMissing

- `with()`：查询主模型前声明 eager loading。
- `load()`：模型已经查出后，再加载关联。
- `loadMissing()`：只加载尚未加载的关联。

项目中列表接口使用 `with()`，详情缓存服务中也在查询时使用 `with()`，这样 Resource 输出时不会再触发额外查询。

## 生产风险

- `whereHas()` 多层嵌套可能生成复杂 SQL，要结合 Explain。
- `like "%keyword%"` 不能很好使用普通索引。
- 大 offset 分页会越来越慢，后续要补 seek pagination。
- Resource 中访问未加载关联可能引发 N+1。
- 批量导入更新指标值时要关注唯一约束和事务。

## 基础问题

1. Eloquent Model 和 Query Builder 有什么区别？
2. `with()` 解决什么问题？
3. `whereHas()` 会生成什么类型的 SQL？
4. 为什么排序字段要白名单？
5. `paginate()` 和 `cursor()` 有什么区别？

## 资深追问

1. 当前 `MetricQuery` 哪些条件可能导致索引失效？
2. Eager Loading 是如何把关联结果匹配回模型的？
3. `latestOfMany()` 如何查询最新指标值？
4. Resource 中访问未加载关联为什么会造成 N+1？
5. 千万级指标值分页如何从 offset 演进到 seek pagination？
