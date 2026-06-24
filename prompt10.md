继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 13.5：查询内核增强 / 加速路由 / 数据权限 / 指标语义统一

## 一、当前项目背景

当前项目是 Laravel 13 + Docker 构建的 BI 分析工具。

前面阶段已经完成或计划完成：

1. 数据源管理。
2. 数据集建模。
3. 查询引擎。
4. 图表管理。
5. 仪表盘管理。
6. Redis 查询缓存。
7. 查询日志。
8. 数据权限。
9. ClickHouse 查询加速。
10. ClickHouse 预聚合表。
11. StarRocks / Doris 作为 OLAP 数据源。
12. 语义层 / 指标库 / 口径治理。
13. 元数据目录 / 血缘 / 影响分析。
14. 数据质量 / 告警规划。

当前问题：

系统能力已经比较多，但查询链路可能存在分散、重复、边界不清晰的问题。

例如：

1. 图表查询、数据集预览、指标查询可能各自生成 SQL。
2. 数据权限可能散落在 Controller / Service / QueryBuilder 中。
3. 指标库和原始字段查询可能没有统一编译流程。
4. ClickHouse 加速、预聚合加速、StarRocks / Doris 查询可能分散处理。
5. 查询缓存 key、query_logs、fallback 逻辑可能没有统一标准。
6. 权限规则、指标规则、加速规则之间可能缺少统一的执行顺序。

本阶段目标不是继续新增大功能，而是把系统核心查询能力收敛成更清晰、更像真实 BI 平台的查询内核。

## 二、本阶段目标

本阶段重点完善四个核心方向：

```text
1. 查询流程统一
2. 加速引擎增强
3. 数据权限管理增强
4. 指标管理和语义层增强
```

最终形成统一链路：

```text
请求入口
    ↓
Chart / Dataset / Metric Query Request
    ↓
Semantic Compiler 语义指标编译
    ↓
Permission Compiler 数据权限编译
    ↓
LogicalQueryPlan 逻辑查询计划
    ↓
Acceleration Router 加速路由
    ↓
Dialect SQL Generator 方言 SQL 生成
    ↓
Query Executor 查询执行
    ↓
Cache Manager 缓存
    ↓
Query Logger 查询日志
    ↓
Response Formatter 结果格式化
```

## 三、本阶段明确不做

本阶段不要做：

1. 不新增大型业务模块。
2. 不重写整个项目。
3. 不推翻已有数据源、数据集、图表、仪表盘 API。
4. 不重写 Vue 前端。
5. 不完整实现机器学习异常检测。
6. 不完整接入 Kafka / Flink / CDC。
7. 不完整做 StarRocks / Doris 物化视图自动改写。
8. 不做复杂图数据库血缘。
9. 不破坏已有 API。
10. 不破坏已有测试。

本阶段只做：

```text
查询内核统一
+
加速路由增强
+
权限编译统一
+
指标语义编译增强
+
日志和缓存标准化
```

## 四、执行前先扫描

请先阅读并理解：

1. plan.md。
2. routes/api.php。
3. Chart 查询接口。
4. Dataset 预览接口。
5. Metric / Semantic Layer 相关接口。
6. QueryService / QueryEngine / SqlGenerator。
7. PermissionService / DataPermissionService / ColumnPermissionService。
8. MetricService / SemanticQueryCompiler。
9. AccelerationQueryRouter。
10. AggregateQueryRouter。
11. ClickHouseSqlGenerator。
12. StarRocks / Doris Dialect。
13. query_logs 表和写入逻辑。
14. Redis 缓存相关代码。
15. Dataset / DatasetField / Metric / Dimension / Chart / Dashboard 模型。
16. 现有测试。
17. README 和 docs。

要求：

1. 先输出当前查询链路梳理。
2. 标记重复逻辑。
3. 标记风险点。
4. 再开始改造。
5. 改造必须渐进，不要一次性推翻。

## 五、核心查询流程设计

目标统一查询入口：

```text
QueryOrchestrator
```

建议新增或整理：

```text
app/Services/Query/
  QueryOrchestrator.php
  LogicalQueryPlan.php
  QueryContext.php
  QueryResult.php
  QueryExecutionMetadata.php
  QueryPlanBuilder.php
  QueryResponseFormatter.php
```

### 1. QueryContext

用于承载本次查询上下文。

字段建议：

```text
user_id
request_source
dataset_id
chart_id nullable
dashboard_id nullable
data_source_id
data_source_type
query_mode
semantic_layer_used
cache_enabled
acceleration_enabled
permission_enabled
debug_enabled
```

request_source：

```text
chart
dashboard
dataset_preview
metric_preview
export
api
```

query_mode：

```text
raw_field
semantic_metric
mixed
```

### 2. LogicalQueryPlan

逻辑查询计划，不绑定具体数据库方言。

字段建议：

```text
dataset_id
data_source_id
data_source_type
table_refs
select_dimensions
select_metrics
computed_metrics
filters
permission_filters
sorts
limit
offset
time_grain
group_by
having
semantic_metrics
semantic_dimensions
metric_versions
required_fields
column_visibility
```

要求：

1. 不直接保存最终 SQL。
2. 所有字段必须来自 dataset_fields / metrics / dimensions。
3. 权限过滤和业务过滤分开保存。
4. 指标版本要记录。
5. 加速路由基于 LogicalQueryPlan 判断。

### 3. QueryResult

统一查询结果。

字段建议：

```text
columns
rows
total nullable
summary nullable
metadata
```

metadata 包含：

```text
cache_hit
semantic_layer_used
permission_applied
acceleration_hit
acceleration_mode
engine_type
duration_ms
query_hash
fallback_used
fallback_reason
```

## 六、查询执行顺序

统一执行顺序必须是：

```text
1. 参数校验
2. 读取 Chart / Dataset / Metric 配置
3. 读取 Dataset 元数据
4. 读取 DataSource
5. 语义指标编译
6. 数据权限编译
7. 构建 LogicalQueryPlan
8. 计算 query_hash
9. 查询 Redis 缓存
10. 加速路由判断
11. SQL 方言生成
12. 执行查询
13. fallback 处理
14. 记录 query_logs
15. 写入缓存
16. 返回标准结果
```

特别注意：

```text
数据权限必须在加速路由之前合并。
```

原因：

如果权限字段无法被预聚合表表达，就不能命中预聚合表，否则可能绕过权限。

## 七、加速引擎增强

当前加速能力可能包括：

```text
Redis 结果缓存
ClickHouse 预聚合表
ClickHouse 明细表
StarRocks / Doris OLAP 数据源直连
原始数据源查询
```

本阶段需要统一成：

```text
AccelerationDecisionPipeline
```

建议新增或整理：

```text
app/Services/Acceleration/
  AccelerationDecisionPipeline.php
  AccelerationDecision.php
  AccelerationCandidate.php
  AccelerationCostEstimator.php
  AccelerationFallbackManager.php
```

### 1. 加速路由优先级

统一优先级：

```text
1. Redis 查询结果缓存
2. ClickHouse 预聚合表 aggregate_table
3. ClickHouse 明细表 detail_table
4. StarRocks / Doris 原生物化视图透明改写，由 OLAP 引擎自己处理
5. 原始数据源查询
```

注意：

StarRocks / Doris 如果作为 data_source.type，是一等 OLAP 数据源，不应该当成 fallback 目标。
它们本身就是当前 dataset 的原始查询引擎。

### 2. AccelerationDecision

字段建议：

```text
use_acceleration
acceleration_mode
engine_type
profile_id nullable
aggregate_definition_id nullable
reason
fallback_allowed
fallback_chain
cache_key_suffix
estimated_cost nullable
```

acceleration_mode：

```text
none
cache
aggregate_table
detail_table
olap_native
raw
```

### 3. 命中规则增强

#### 预聚合表命中条件

必须满足：

1. aggregate definition active。
2. 查询维度是预聚合维度子集或完全匹配。
3. 查询指标都能由预聚合指标表达。
4. filter 字段存在于预聚合表，或可安全下推。
5. permission filter 字段存在于预聚合表。
6. sort 字段存在。
7. 时间颗粒兼容。
8. avg / countDistinct 边界处理正确。
9. 不需要明细字段。
10. 当前指标版本兼容。

#### ClickHouse 明细表命中条件

必须满足：

1. detail profile active。
2. 所有字段都能映射。
3. 权限字段都能映射。
4. 当前计算字段可表达。
5. ClickHouse 连接正常。

#### OLAP Native

如果 data_source_type 是：

```text
starrocks
doris
clickhouse
```

则认为当前原始数据源本身就是 OLAP 引擎。

query_logs 中记录：

```text
acceleration_mode = olap_native
engine_type = starrocks / doris / clickhouse
```

但不要错误地标记为 ClickHouse acceleration profile 命中。

### 4. fallback 规则

fallback 顺序：

```text
aggregate_table 失败
    ↓
detail_table
    ↓
raw source query

detail_table 失败
    ↓
raw source query

olap_native 失败
    ↓
直接返回错误
```

说明：

StarRocks / Doris 直连失败时，不应该 fallback 到 MySQL，除非 dataset 明确配置了 fallback_source。

### 5. 加速效果记录

query_logs 需要标准记录：

```text
cache_hit
acceleration_hit
acceleration_mode
acceleration_profile_id
aggregate_definition_id
engine_type
data_source_type
fallback_used
fallback_reason
raw_duration_ms
accelerated_duration_ms
total_duration_ms
query_hash
logical_plan_hash
```

如果字段不存在，新增 migration。
如果已存在，复用并整理写入逻辑。

## 八、查询缓存标准化

新增或整理：

```text
QueryCacheManager
```

职责：

1. 生成缓存 key。
2. 查询缓存。
3. 写入缓存。
4. 清理缓存。
5. 支持 raw / semantic / aggregate / detail / olap_native 区分。
6. 支持权限版本和指标版本。

缓存 key 必须包含：

```text
chart_id nullable
dataset_id
user_id 或 permission_hash
query_hash
semantic_metric_versions_hash
acceleration_mode
acceleration_version nullable
data_source_type
```

建议格式：

```text
bi:query:user:{user_id}:dataset:{dataset_id}:mode:{query_mode}:engine:{engine_type}:acc:{acceleration_mode}:mv:{metric_versions_hash}:pv:{permission_hash}:q:{query_hash}
```

要求：

1. 不同权限用户不能共享错误缓存。
2. 指标口径变更后缓存失效。
3. 加速表重建后缓存失效。
4. 数据集字段变更后缓存失效。
5. 图表配置变更后缓存失效。

## 九、数据权限管理增强

当前权限应至少包括：

```text
资源权限
行级权限
列级权限
```

本阶段要统一为：

```text
PermissionCompileResult
```

建议新增或整理：

```text
app/Services/Permission/
  PermissionCompiler.php
  PermissionContext.php
  PermissionCompileResult.php
  PermissionRuleValidator.php
  PermissionFieldMapper.php
```

### 1. PermissionContext

字段建议：

```text
user_id
roles
departments
dataset_id
data_source_id
chart_id nullable
dashboard_id nullable
request_source
```

### 2. PermissionCompileResult

字段建议：

```text
resource_allowed
denied_reason nullable
row_filters
column_rules
masked_fields
hidden_fields
permission_hash
required_permission_fields
```

### 3. 行级权限

支持规则：

```text
field = fixed_value
field in values
field = current_user_id
field in current_user_departments
field between values
```

要求：

1. 权限字段必须来自 dataset_fields。
2. 权限 filter 不允许被前端覆盖。
3. 权限 filter 必须在 LogicalQueryPlan 中单独保存。
4. 权限字段无法映射到加速表时，加速表不能命中。
5. 权限规则变更后 permission_hash 改变。

### 4. 列级权限

支持：

```text
visible
hidden
masked
```

要求：

1. hidden 字段不能进入 select。
2. masked 字段如果出现在明细预览里，需要脱敏。
3. 聚合查询中如果指标依赖 hidden 字段，需要拒绝或返回无权限。
4. 图表选择指标时，如果指标依赖无权限字段，应提示无权限。

### 5. 资源权限

检查对象：

```text
data_source
dataset
metric
chart
dashboard
acceleration_profile
aggregate_definition
```

要求：

1. QueryOrchestrator 开始阶段必须检查 dataset 权限。
2. 图表查询必须检查 chart 权限。
3. 仪表盘查询必须检查 dashboard 权限。
4. 指标查询必须检查 metric 权限。
5. Explain / Debug 查询也必须检查权限。

### 6. 权限调试接口

新增可选 API：

```text
POST /api/permissions/debug-query
```

输入：

```json
{
  "dataset_id": 1,
  "chart_id": 2
}
```

返回：

```json
{
  "resource_allowed": true,
  "row_filters": [],
  "hidden_fields": [],
  "masked_fields": [],
  "permission_hash": "..."
}
```

要求：

1. 仅管理员可用。
2. 不泄露无权限对象细节。
3. 用于调试为什么某用户看不到数据。

## 十、指标管理和语义层增强

当前已有或计划有：

```text
metrics
metric_versions
dimensions
metric_dependencies
metric_usages
SemanticQueryCompiler
```

本阶段重点增强指标在查询链路中的稳定性。

### 1. 指标解析标准化

SemanticQueryCompiler 输出必须进入 LogicalQueryPlan。

基础指标：

```text
sales_amount = sum(amount)
```

复合指标：

```text
avg_order_amount = sales_amount / order_count
```

输出应拆成：

```text
base_metrics:
  - sum(amount) as sales_amount
  - count(order_id) as order_count

computed_metrics:
  - sales_amount / order_count as avg_order_amount
```

### 2. 指标版本

查询时记录：

```text
metric_code
metric_id
metric_version
formula
source_field
aggregate_function
```

query_logs 记录：

```text
semantic_metrics_json
metric_versions_json
semantic_layer_used = true
```

缓存 key 包含：

```text
metric_versions_hash
```

### 3. 指标权限

如果用户无权访问某指标：

```text
查询拒绝
```

如果用户有指标权限，但无权访问指标依赖字段：

```text
查询拒绝，并返回明确错误
```

如果复合指标依赖的基础指标无权限：

```text
查询拒绝
```

### 4. 指标依赖校验

每次指标保存时：

1. 校验 source_field 存在。
2. 校验 formula 引用的指标存在。
3. 校验无循环依赖。
4. 保存 metric_dependencies。
5. 创建 metric_versions。
6. 清理相关图表缓存。
7. 记录影响分析。

### 5. 指标和加速表关系

加速表命中时，需要判断：

1. 预聚合表是否支持当前指标。
2. 指标版本是否和预聚合表构建时一致。
3. 复合指标是否可以由预聚合字段二次计算。
4. 指标依赖字段是否都存在。
5. 指标权限字段是否能表达。

如果不满足：

```text
fallback detail_table 或 raw
```

### 6. 指标调试接口

新增可选 API：

```text
POST /api/metrics/compile-debug
```

输入：

```json
{
  "dataset_id": 1,
  "metrics": ["sales_amount", "order_count", "avg_order_amount"],
  "dimensions": ["province"]
}
```

返回：

```json
{
  "base_metrics": [],
  "computed_metrics": [],
  "dependencies": [],
  "metric_versions": [],
  "errors": []
}
```

要求：

1. 仅管理员或指标管理员可用。
2. 不执行真实查询。
3. 用于调试指标公式。

## 十一、SQL 方言层增强

确保所有查询都通过统一 Dialect 生成。

涉及：

```text
MySQL
PostgreSQL
ClickHouse
StarRocks
Doris
```

Dialect 接口建议包含：

```php
quoteIdentifier(string $identifier): string;

compileDateGrain(string $field, string $grain): string;

compileAggregate(string $function, string $field): string;

compileFilter(string $field, string $operator, mixed $value): CompiledCondition;

compileLimit(?int $limit, ?int $offset): string;

supportsAggregate(string $function): bool;

supportsDateGrain(string $grain): bool;

getName(): string;
```

要求：

1. QueryService 不直接判断大量 engine_type。
2. 字段引用由 Dialect quote。
3. 函数差异由 Dialect 处理。
4. 不支持的函数返回明确错误。
5. StarRocks / Doris 通过 MySQL 协议执行，但 Dialect 独立。

## 十二、Explain 和查询调试增强

新增统一查询调试接口：

```text
POST /api/query/debug
```

仅管理员可用。

返回：

```json
{
  "context": {},
  "semantic_compile_result": {},
  "permission_compile_result": {},
  "logical_plan": {},
  "acceleration_decision": {},
  "generated_sql": "",
  "bindings": [],
  "cache_key": "",
  "warnings": []
}
```

要求：

1. 默认不执行真实查询。
2. 可选 execute=true 时执行 explain。
3. 不返回数据库密码。
4. 不允许前端传任意 SQL。
5. 只基于 chart_id / dataset_id / metric config 生成。

## 十三、API 设计

新增或整理以下接口。

### 1. 查询调试

```text
POST /api/query/debug
POST /api/query/explain
```

### 2. 权限调试

```text
POST /api/permissions/debug-query
```

### 3. 指标编译调试

```text
POST /api/metrics/compile-debug
```

### 4. 加速决策调试

```text
POST /api/acceleration/debug-decision
```

返回：

```json
{
  "candidates": [
    {
      "mode": "aggregate_table",
      "eligible": false,
      "reason": "permission field city not found in aggregate table"
    },
    {
      "mode": "detail_table",
      "eligible": true
    }
  ],
  "selected": "detail_table"
}
```

要求：

1. 这些 debug 接口都必须限制管理员。
2. 不影响正式查询。
3. 不能执行危险 SQL。

## 十四、测试重点

新增或补充测试：

### 1. 查询流程测试

1. 原始字段图表查询正常。
2. 语义指标图表查询正常。
3. 数据集预览正常。
4. 指标预览正常。
5. 旧图表配置兼容。

### 2. 权限测试

1. 无 dataset 权限时拒绝。
2. 行级权限自动合并。
3. 列级 hidden 字段不能查询。
4. 指标依赖 hidden 字段时拒绝。
5. 权限字段缺失时预聚合不命中。

### 3. 指标测试

1. 基础指标编译。
2. 复合指标编译。
3. 循环依赖拒绝。
4. 指标版本进入缓存 key。
5. 指标变更清理缓存。

### 4. 加速测试

1. 命中预聚合表。
2. 预聚合不满足时 fallback 明细表。
3. 明细表不满足时 fallback raw。
4. StarRocks / Doris 数据源标记 olap_native。
5. query_logs 记录 acceleration_mode。

### 5. 缓存测试

1. 相同查询命中缓存。
2. 不同权限用户不共享错误缓存。
3. 指标版本变化后缓存变化。
4. 加速表版本变化后缓存变化。

## 十五、文档要求

新增或更新：

```text
docs/bi-query-core.md
```

内容包括：

1. BI 查询内核总体设计。
2. QueryOrchestrator 职责。
3. LogicalQueryPlan 结构。
4. 语义指标编译流程。
5. 数据权限编译流程。
6. 加速路由决策流程。
7. SQL 方言生成流程。
8. 缓存 key 设计。
9. query_logs 标准字段。
10. fallback 机制。
11. Debug 接口说明。
12. 当前实现边界。
13. 后续优化方向。

重点说明：

```text
为什么数据权限必须在加速路由前合并。
为什么语义指标必须先编译成逻辑计划。
为什么缓存 key 需要包含权限 hash、指标版本和加速版本。
为什么 StarRocks / Doris 作为数据源和 ClickHouse 加速层是两条不同路线。
```

## 十六、验收标准

完成后需要满足：

1. 有统一 QueryOrchestrator 或等价统一入口。
2. 有 LogicalQueryPlan 或等价逻辑查询计划结构。
3. 图表查询通过统一流程执行。
4. 数据集预览尽量通过统一流程执行。
5. 语义指标查询通过统一流程执行。
6. 数据权限通过 PermissionCompiler 统一合并。
7. 加速路由通过统一 Decision Pipeline 判断。
8. query_logs 字段记录完整。
9. 缓存 key 包含权限 hash、指标版本、加速模式。
10. 预聚合命中必须考虑权限字段。
11. StarRocks / Doris 作为 olap_native 数据源被正确记录。
12. 指标变更会影响缓存和 usage。
13. Debug 接口可返回查询计划、权限结果、加速决策。
14. 旧图表配置继续可用。
15. 不破坏已有 API。
16. migration 可以正常执行和回滚。
17. 尽量通过 php artisan route:list。
18. 尽量通过 php artisan test。
19. 如果有前端，尽量通过 npm run build。

## 十七、运行命令

尽量运行：

```text
php artisan migrate
php artisan route:list
php artisan test
```

如果有前端：

```text
cd frontend
npm run build
```

如果有队列和缓存相关验证：

```text
php artisan queue:work
php artisan cache:clear
```

## 十八、代码要求

1. 不要推翻原有项目。
2. 统一查询流程要渐进接入。
3. 保持旧 API 兼容。
4. 字段必须来自元数据。
5. 指标必须来自指标库。
6. 权限必须后端强制合并。
7. 加速不能绕过权限。
8. SQL 方言差异不能散落在业务代码。
9. 缓存 key 必须考虑权限和指标版本。
10. query_logs 必须记录关键决策。
11. Debug 接口必须受权限保护。
12. 文档必须说明设计边界。

## 十九、当前阶段不做内容

本阶段不做：

1. 新的大型 BI 功能。
2. 完整商业级查询优化器。
3. 自动 SQL AST 全量解析。
4. 机器学习加速推荐。
5. Flink / Kafka / CDC。
6. 完整审批流。
7. 大规模前端重构。

## 二十、最终输出要求

任务完成后，请输出：

1. 本次完成内容。
2. 当前查询链路梳理。
3. 修改文件列表。
4. 新增或修改的核心类。
5. 新增 migration 列表。
6. 新增 API 列表。
7. QueryOrchestrator 说明。
8. LogicalQueryPlan 说明。
9. PermissionCompiler 说明。
10. SemanticQueryCompiler 接入说明。
11. AccelerationDecisionPipeline 说明。
12. 缓存 key 说明。
13. query_logs 字段说明。
14. Debug 接口说明。
15. 运行过的命令。
16. 测试结果。
17. 当前实现边界。
18. 下一阶段建议。