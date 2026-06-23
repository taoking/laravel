继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 10.2：预聚合表 / 物化视图加速

## 一、当前项目背景

当前项目是 Laravel 13 + Docker 构建的 BI 分析工具。

前一阶段 Phase 10.1 已经实现或计划实现：

1. ClickHouse 服务接入。
2. acceleration_profiles 表。
3. acceleration_columns 表。
4. acceleration_tasks 表。
5. ClickHouse 明细表构建。
6. 数据集数据同步到 ClickHouse。
7. 图表查询路由到 ClickHouse。
8. 加速失败 fallback 原始查询。
9. query_logs 记录 acceleration_hit、fallback_used、fallback_reason。
10. Redis 查询缓存区分 raw 和 acceleration 查询。

本阶段目标是在 ClickHouse 明细表加速基础上，继续实现：

```text
预聚合表 / 物化视图加速
```

重点是为高频图表、仪表盘、固定维度指标组合提前计算汇总结果，减少每次查询都从明细表 group by 的成本。

## 二、本阶段目标

实现一套最小可用的预聚合加速能力：

1. 支持为数据集创建预聚合配置。
2. 支持根据维度、指标、时间颗粒生成预聚合表。
3. 支持从 ClickHouse 明细表构建预聚合表。
4. 支持图表查询时判断是否可以命中预聚合表。
5. 预聚合命中时查询预聚合表。
6. 预聚合不命中时 fallback 到 ClickHouse 明细表。
7. ClickHouse 明细表也不可用时 fallback 到原始数据源。
8. query_logs 记录 acceleration_mode = aggregate_table 或 detail_table。
9. 记录预聚合构建任务状态。
10. 文档说明当前边界和后续物化视图扩展方向。

## 三、本阶段明确不做

本阶段不要做：

1. 不做自动推荐算法。
2. 不做复杂 SQL 优化器。
3. 不做 Kafka / Debezium / Flink。
4. 不做实时 CDC。
5. 不做 StarRocks / Doris 完整实现。
6. 不做多层 Cube 自动生成。
7. 不做复杂跨数据集 Join 预聚合。
8. 不做商业 BI 级别的智能建模器。
9. 不重写 Phase 10.1 的 ClickHouse 明细表加速。
10. 不破坏现有图表、仪表盘、查询缓存、权限、查询日志流程。

本阶段只做：

```text
基于 ClickHouse 明细表的预聚合表加速最小闭环
```

## 四、执行前先扫描

请先阅读：

1. plan.md
2. docs/bi-acceleration-clickhouse.md
3. 当前 Acceleration 模块代码
4. AccelerationProfileService
5. AccelerationSchemaService
6. AccelerationSyncService
7. AccelerationQueryRouter
8. AccelerationEligibilityChecker
9. ClickHouseSqlGenerator
10. ClickHouseClient
11. BuildAccelerationTableJob
12. query_logs 相关代码
13. Dataset / Chart / Dashboard 相关 Model
14. Chart 查询接口
15. 数据权限和缓存相关代码
16. 当前 migrations
17. docker-compose.yml
18. README.md

要求：

1. 先理解 Phase 10.1 已有实现。
2. 不要重复创建相同职责的类。
3. 预聚合加速要作为 ClickHouse 明细表加速的增强层。
4. 原有 detail_table 加速必须继续可用。

## 五、核心概念

### 1. 明细表加速

上一阶段的 ClickHouse 明细表类似：

```text
acc_dataset_1_detail
```

它保存原始明细数据。

图表查询时仍然需要：

```sql
select province, sum(amount)
from acc_dataset_1_detail
where order_date >= '2026-01-01'
group by province
```

### 2. 预聚合表加速

本阶段新增预聚合表，例如：

```text
acc_dataset_1_agg_month_province
```

它提前保存：

```text
month
province
sales_amount_sum
order_count
quantity_sum
```

图表查询可以直接查：

```sql
select month, province, sales_amount_sum
from acc_dataset_1_agg_month_province
where month >= '2026-01-01'
```

这样可以减少每次扫描明细表和 group by 的开销。

## 六、数据库表设计

### 1. acceleration_profiles 扩展

当前 acceleration_profiles 已有：

```text
mode = detail_table
```

本阶段增加：

```text
mode = aggregate_table
```

要求：

1. detail_table 表示明细表加速。
2. aggregate_table 表示预聚合表加速。
3. aggregate_table profile 需要关联一个 source_detail_profile_id。
4. 如果已有 config_json，可以把 source_detail_profile_id 放入 config_json。
5. 更推荐新增字段 source_profile_id，指向明细表 profile。

如需新增字段：

```text
source_profile_id nullable
aggregate_grain nullable
aggregate_dimensions_json nullable
aggregate_metrics_json nullable
```

字段说明：

```text
source_profile_id：当前预聚合表基于哪个 detail_table profile 构建
aggregate_grain：day / month / year / none
aggregate_dimensions_json：参与 group by 的维度字段
aggregate_metrics_json：提前聚合的指标配置
```

### 2. 新增 acceleration_aggregate_definitions 表

如果不想继续扩展 profiles，可以新增独立表。

推荐新增：

```text
acceleration_aggregate_definitions
```

字段建议：

```text
id
dataset_id
detail_profile_id
aggregate_profile_id
name
status                  disabled / building / active / failed
target_database
target_table
time_field
time_grain              none / day / month / year
dimensions_json
metrics_json
filters_json
refresh_type            manual / scheduled
last_refresh_at
last_success_at
last_error_message
row_count
version
created_by
created_at
updated_at
```

说明：

1. detail_profile_id 指向 ClickHouse 明细表 profile。
2. aggregate_profile_id 可选，如果沿用 acceleration_profiles 管理物理表，则关联 aggregate_table profile。
3. dimensions_json 保存 group by 维度。
4. metrics_json 保存聚合指标，例如 sum(amount)、count(id)、avg(price)。
5. filters_json 可选，用于固定过滤条件。
6. version 用于缓存失效。

### 3. 新增 acceleration_aggregate_columns 表

字段建议：

```text
id
aggregate_definition_id
source_field_name
target_field_name
column_role             dimension / metric / time_grain
aggregate_function      sum / avg / count / countDistinct / min / max / none
source_type
target_type
created_at
updated_at
```

说明：

1. dimension 字段直接作为 group by 字段。
2. metric 字段保存聚合后的结果字段。
3. time_grain 字段用于保存 day/month/year 这种时间颗粒结果。
4. 图表查询命中预聚合表时，只能使用这些字段。

### 4. acceleration_tasks 扩展

当前 acceleration_tasks 已支持 full_sync / rebuild。

本阶段增加 task_type：

```text
build_aggregate
refresh_aggregate
```

要求：

1. 构建预聚合表走队列。
2. 任务状态继续复用 pending / running / success / failed。
3. 构建失败记录 error_message。
4. 构建成功更新 aggregate definition 状态和 row_count。

### 5. query_logs 扩展

如果已有字段不够，增加：

```text
acceleration_mode
aggregate_definition_id
aggregate_table
detail_fallback_used
```

说明：

```text
acceleration_mode：
- none
- detail_table
- aggregate_table

aggregate_definition_id：
命中的预聚合配置 ID

detail_fallback_used：
预聚合不命中或失败后，是否 fallback 到 ClickHouse 明细表
```

## 七、配置文件调整

更新：

```text
config/bi_acceleration.php
```

增加：

```php
'aggregate' => [
    'enabled' => env('BI_AGGREGATE_ACCELERATION_ENABLED', true),
    'max_dimensions' => env('BI_AGGREGATE_MAX_DIMENSIONS', 5),
    'max_metrics' => env('BI_AGGREGATE_MAX_METRICS', 10),
    'fallback_to_detail' => true,
    'fallback_to_source' => true,
],
```

.env.example 增加：

```text
BI_AGGREGATE_ACCELERATION_ENABLED=true
BI_AGGREGATE_MAX_DIMENSIONS=5
BI_AGGREGATE_MAX_METRICS=10
```

## 八、服务类设计

新增或扩展：

```text
app/Services/Acceleration/
```

建议新增：

```text
AggregateDefinitionService
AggregateSchemaService
AggregateBuildService
AggregateEligibilityChecker
AggregateSqlGenerator
AggregateQueryRouter
```

### 1. AggregateDefinitionService

负责：

1. 创建预聚合定义。
2. 保存维度配置。
3. 保存指标配置。
4. 保存时间颗粒配置。
5. 启用 / 禁用预聚合定义。
6. 查找 dataset 下 active 的预聚合定义。
7. 更新 version。
8. 更新 row_count 和状态。

### 2. AggregateSchemaService

负责：

1. 根据定义生成 ClickHouse 预聚合表名。
2. 根据 dimensions_json 和 metrics_json 生成字段。
3. 生成 CREATE TABLE SQL。
4. 删除旧表。
5. 重建预聚合表。

表名示例：

```text
acc_dataset_{dataset_id}_agg_{definition_id}_v{version}
```

ClickHouse 建表策略：

```text
MergeTree
ORDER BY (time_grain_field, dimension_1, dimension_2)
```

如果没有时间颗粒：

```text
MergeTree
ORDER BY (dimension_1, dimension_2)
```

如果没有维度：

```text
ORDER BY tuple()
```

### 3. AggregateBuildService

负责从 ClickHouse 明细表构建预聚合表。

流程：

```text
读取 aggregate definition
    ↓
读取 detail profile
    ↓
确保 detail profile active
    ↓
生成 CREATE TABLE
    ↓
生成 INSERT INTO aggregate_table SELECT ... FROM detail_table GROUP BY ...
    ↓
执行 ClickHouse SQL
    ↓
统计 row_count
    ↓
更新状态 active
    ↓
清理相关缓存
```

示例 SQL：

```sql
insert into acc_dataset_1_agg_month_province
select
    toStartOfMonth(order_date) as month,
    province,
    sum(amount) as amount_sum,
    count() as order_count
from acc_dataset_1_detail
group by
    toStartOfMonth(order_date),
    province;
```

要求：

1. 字段名必须来自 acceleration_columns。
2. 维度字段必须存在。
3. 指标字段必须存在。
4. 聚合函数必须白名单。
5. 所有表名必须由后端生成。
6. 不允许前端传物理表名。
7. 构建任务必须走队列。

### 4. AggregateEligibilityChecker

负责判断当前图表查询是否可以命中预聚合表。

可命中条件：

1. BI_AGGREGATE_ACCELERATION_ENABLED = true。
2. dataset 存在 active aggregate definition。
3. 当前查询维度是预聚合维度的子集或完全匹配。
4. 当前查询指标都在 aggregate metrics 中。
5. 当前查询 time_grain 与预聚合 time_grain 兼容。
6. 当前 filter 字段在预聚合表中存在，或者可以安全下推。
7. 当前 sort 字段在预聚合表中存在。
8. 当前权限 filter 字段在预聚合表中存在。
9. 当前查询不要求明细字段。
10. 当前查询没有预聚合无法表达的计算字段。

不可命中情况：

1. 维度超出预聚合表维度。
2. 指标不在预聚合指标内。
3. 时间颗粒比预聚合更细。
4. filter 字段预聚合表不存在。
5. 权限字段预聚合表不存在。
6. 查询需要原始明细行。
7. 聚合函数不兼容，例如 avg 的二次聚合没有 count/sum 支撑。

注意 avg：

如果预聚合中只有 avg(amount)，后续再按更粗维度聚合 avg 会不准确。
更稳的做法是预聚合时保存：

```text
amount_sum
amount_count
```

查询 avg 时用：

```text
amount_sum / amount_count
```

第一版可以：

1. avg 指标只支持完全匹配维度查询。
2. 或者自动存 sum + count。
3. 文档中说明 avg 的边界。

### 5. AggregateSqlGenerator

负责生成查询预聚合表的 SQL。

支持：

1. select dimensions
2. select metrics
3. where filters
4. permission filters
5. group by 可选
6. order by
7. limit

如果当前查询维度是预聚合维度子集，需要二次聚合。

例如预聚合表是：

```text
month + province + city
```

当前查询只要：

```text
month + province
```

则需要：

```sql
select
    month,
    province,
    sum(amount_sum) as amount_sum
from aggregate_table
group by month, province
```

要求：

1. sum、count、min、max 可以二次聚合。
2. avg 要谨慎处理。
3. countDistinct 第一版可以只支持完全匹配，不做二次聚合。
4. 不支持的情况 fallback 到 detail_table。

### 6. AggregateQueryRouter

负责：

1. 在 AccelerationQueryRouter 之前或内部优先判断预聚合。
2. 如果命中 aggregate_table，查询预聚合表。
3. 如果不命中，交给 detail_table 路由。
4. 如果 aggregate 查询失败，fallback detail_table。
5. 如果 detail_table 也失败，再 fallback 原始数据源。
6. 返回 metadata。

metadata 包括：

```text
acceleration_hit
acceleration_mode = aggregate_table
aggregate_definition_id
acceleration_profile_id
fallback_used
fallback_reason
detail_fallback_used
accelerated_duration_ms
```

## 九、API 设计

新增接口前先检查项目已有路由风格。

建议新增：

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

请求示例：

```json
{
  "name": "销售月度省份预聚合",
  "dataset_id": 1,
  "detail_profile_id": 1,
  "time_field": "order_date",
  "time_grain": "month",
  "dimensions": ["province"],
  "metrics": [
    {"field": "amount", "aggregate": "sum", "alias": "amount_sum"},
    {"field": "id", "aggregate": "count", "alias": "order_count"}
  ],
  "filters": []
}
```

返回内容：

```json
{
  "id": 1,
  "dataset_id": 1,
  "status": "active",
  "target_table": "acc_dataset_1_agg_1_v1",
  "time_grain": "month",
  "dimensions": ["province"],
  "metrics": [
    {"field": "amount", "aggregate": "sum", "target_field": "amount_sum"}
  ],
  "row_count": 120,
  "last_success_at": "2026-06-23 12:00:00"
}
```

## 十、前端页面建议

如果 Vue 前端已经完成，可以增加：

```text
查询加速管理 -> 预聚合配置
```

页面功能：

1. 数据集列表中展示是否已有明细加速。
2. 预聚合配置列表。
3. 新增预聚合配置。
4. 选择 detail profile。
5. 选择时间字段。
6. 选择时间颗粒：none / day / month / year。
7. 选择维度字段。
8. 选择指标字段和聚合函数。
9. 构建预聚合表按钮。
10. 刷新预聚合表按钮。
11. 查看构建任务状态。
12. 查看命中日志。
13. 展示 row_count、last_success_at、status。
14. 说明 avg、countDistinct 的当前限制。

如果前端未完成，本阶段只做 API 和文档。

## 十一、查询路由优先级

调整查询路由为：

```text
图表查询
    ↓
Redis 查询结果缓存
    ↓
AggregateQueryRouter
    ↓
命中预聚合？
    ├── 是：查询 aggregate_table
    │       ↓
    │     写 query_logs acceleration_mode = aggregate_table
    │       ↓
    │     返回
    │
    └── 否：
            ↓
        Detail AccelerationQueryRouter
            ↓
        命中 ClickHouse 明细表？
            ├── 是：查询 detail_table
            │       ↓
            │     写 query_logs acceleration_mode = detail_table
            │       ↓
            │     返回
            │
            └── 否：
                    ↓
                原始数据源查询
                    ↓
                写 query_logs acceleration_mode = none
```

要求：

1. 预聚合优先级高于明细表。
2. 预聚合失败可以 fallback 明细表。
3. 明细表失败可以 fallback 原始数据源。
4. 所有 fallback 都要记录原因。
5. Redis 缓存 key 需要区分 aggregate_table 和 detail_table。

## 十二、缓存 key 调整

预聚合命中时：

```text
bi:chart:{chart_id}:user:{user_id}:agg:{aggregate_definition_id}:v:{version}:query:{query_hash}
```

明细表命中时：

```text
bi:chart:{chart_id}:user:{user_id}:acc:{profile_id}:v:{version}:query:{query_hash}
```

原始查询时：

```text
bi:chart:{chart_id}:user:{user_id}:raw:query:{query_hash}
```

要求：

1. 预聚合表重建后 version + 1。
2. version 变化后旧缓存自然失效。
3. 数据权限变化仍然要清理用户相关缓存。
4. 图表配置变化仍然要清理图表缓存。
5. 数据集结构变化要禁用或重建相关预聚合定义。

## 十三、权限要求

预聚合不能绕过数据权限。

必须满足：

1. 资源权限仍然生效。
2. 行级权限仍然生效。
3. 列级权限仍然生效。
4. 权限字段如果不在预聚合表中，必须 fallback 到 detail_table。
5. detail_table 也无法满足时，fallback 原始查询或拒绝。
6. 不允许前端指定绕过权限。
7. 不允许前端直接指定预聚合物理表名。
8. 权限条件必须由后端合并。

特别注意：

如果某个用户的数据权限是：

```text
city = 深圳
```

但预聚合表只有：

```text
month + province
```

没有 city 字段，那么不能使用这个预聚合表，否则会造成权限绕过。
这种情况必须 fallback 到 ClickHouse 明细表或原始查询。

## 十四、预聚合构建规则

第一版采用手动构建。

流程：

```text
创建 aggregate definition
    ↓
点击 build
    ↓
创建 acceleration_task
    ↓
状态 running
    ↓
根据 detail_table 生成 aggregate_table
    ↓
insert into aggregate_table select ... group by ...
    ↓
统计 row_count
    ↓
状态 active
    ↓
version + 1
    ↓
清理相关缓存
```

暂不做自动定时刷新。
可以在字段中预留 refresh_type = manual / scheduled。

## 十五、测试数据建议

继续使用 sales_orders 数据集。

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

建议创建以下预聚合：

### 1. 月度省份销售预聚合

```text
time_field: order_date
time_grain: month
dimensions: province
metrics:
  - sum(amount) as amount_sum
  - count(id) as order_count
  - sum(quantity) as quantity_sum
```

适配图表：

1. 按月份统计销售额。
2. 按省份统计销售额。
3. 按月份 + 省份统计销售额。

### 2. 日度产品分类预聚合

```text
time_field: order_date
time_grain: day
dimensions: product_category
metrics:
  - sum(amount)
  - count(id)
```

适配图表：

1. 最近 30 天销售趋势。
2. 产品分类销售排行。

### 3. 部门月度预聚合

```text
time_field: order_date
time_grain: month
dimensions: department_id
metrics:
  - sum(amount)
  - count(id)
```

用于测试数据权限。

## 十六、验收标准

完成后需要满足：

1. 可以创建 aggregate definition。
2. 可以基于 detail profile 构建预聚合表。
3. 可以在 ClickHouse 中生成 aggregate_table。
4. acceleration_tasks 能记录 build_aggregate 状态。
5. 构建成功后 aggregate definition 状态为 active。
6. 图表查询符合预聚合条件时，命中 aggregate_table。
7. 图表查询不符合预聚合条件时，fallback detail_table。
8. detail_table 不可用时，fallback 原始查询。
9. query_logs 能记录 acceleration_mode = aggregate_table。
10. query_logs 能记录 aggregate_definition_id。
11. Redis 缓存 key 能区分 aggregate_table / detail_table / raw。
12. 数据权限字段缺失时不能命中预聚合。
13. avg / countDistinct 的限制在文档中说明。
14. docs 中说明预聚合表构建和查询路由。
15. 不破坏 Phase 10.1 明细表加速。
16. 不破坏原有图表查询。
17. 尽量通过 php artisan test。
18. 尽量通过 php artisan route:list。
19. migration 可以正常执行和回滚。

## 十七、文档要求

新增或更新：

```text
docs/bi-acceleration-aggregate.md
```

内容包括：

1. 为什么需要预聚合。
2. 明细表加速和预聚合加速的区别。
3. 适合预聚合的场景。
4. 不适合预聚合的场景。
5. aggregate definition 表说明。
6. aggregate columns 表说明。
7. 预聚合表命名规则。
8. 预聚合表构建流程。
9. 查询命中规则。
10. fallback 机制。
11. 权限如何生效。
12. avg / countDistinct 当前限制。
13. 缓存 key 设计。
14. query_logs 字段说明。
15. 后续扩展：物化视图、定时刷新、自动推荐、StarRocks/Doris。

## 十八、代码要求

1. 不要大范围重构已有查询引擎。
2. 预聚合逻辑必须是可选增强。
3. 预聚合失败不能影响原始查询。
4. SQL 字段必须来自元数据映射。
5. 聚合函数必须白名单。
6. 不允许前端传物理表名。
7. 不允许拼接未校验用户输入。
8. 权限 filter 必须在预聚合查询中生效，无法生效时必须 fallback。
9. 构建任务必须走队列。
10. migration 必须可回滚。
11. 配置必须走 env。
12. 文档必须说明边界。

## 十九、运行命令

尽量运行：

```text
php artisan migrate
php artisan route:list
php artisan test
```

如果有队列：

```text
php artisan queue:work
```

如果使用 Docker：

```text
docker compose ps
docker compose up -d clickhouse
```

如果有前端：

```text
cd frontend
npm run build
```

## 二十、最终输出要求

任务完成后，请输出：

1. 本次完成内容。
2. 修改文件列表。
3. 新增 migration 列表。
4. 新增 Model 列表。
5. 新增 Service 列表。
6. 新增 Job 列表。
7. 新增 API 列表。
8. 新增或更新文档位置。
9. 预聚合表构建流程。
10. 查询路由优先级说明。
11. fallback 机制说明。
12. 权限边界说明。
13. 缓存 key 变化说明。
14. query_logs 增强字段说明。
15. 运行过的命令。
16. 测试结果。
17. 当前实现边界。
18. 下一阶段建议。