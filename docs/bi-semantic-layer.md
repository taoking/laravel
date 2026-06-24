# BI 语义层 / 指标库 / 口径治理

本文档对应 Phase 11。当前实现是在已有数据源、数据集、查询引擎、图表、仪表盘、查询缓存和查询日志基础上，增加一套最小可用的语义层能力，让图表可以选择统一指标和维度，而不是在每个图表里重复维护 `sum(amount)` 这类字段口径。

## 目标

- 让业务指标具备统一口径，减少不同图表重复维护聚合逻辑造成的口径漂移。
- 统一管理指标分类、指标定义、维度定义和数据集字段绑定。
- 支持基础指标、派生指标、复合指标。
- 支持指标版本、依赖关系、使用记录和影响分析。
- 支持图表配置选择语义指标和语义维度。
- 查询执行时把语义配置编译为现有 Query Engine 的维度/指标协议。
- 查询日志记录语义层使用情况、指标列表、维度列表和指标版本。
- 保持原有 dataset/chart/query/cache/acceleration/OLAP 主流程兼容。

## 数据模型

新增表：

- `metric_categories`：指标分类，支持父子级和排序。
- `metrics`：指标主表，保存数据集、编码、类型、聚合函数、字段绑定、公式、单位、格式、状态和版本。
- `metric_versions`：指标版本快照，指标口径变更时记录定义。
- `dimensions`：维度主表，保存维度编码、字段绑定、类型、时间颗粒和状态。
- `metric_dependencies`：指标依赖，记录指标依赖字段或其他指标。
- `metric_usages`：指标使用记录，第一版记录图表配置中的语义指标。

`query_logs` 新增字段：

- `semantic_layer_used`
- `semantic_metrics_json`
- `semantic_dimensions_json`
- `metric_versions_json`

## 指标类型

基础指标：

```json
{
  "dataset_id": 1,
  "name": "销售额",
  "code": "sales_amount",
  "metric_type": "base",
  "aggregate_function": "sum",
  "source_field": "amount",
  "status": "active"
}
```

复合指标：

```json
{
  "dataset_id": 1,
  "name": "客单价",
  "code": "avg_order_amount",
  "metric_type": "compound",
  "aggregate_function": "expression",
  "formula": "sales_amount / order_count",
  "status": "active"
}
```

当前公式解析器只允许：

- 指标编码。
- 数字。
- `+`、`-`、`*`、`/`。
- 小括号。

不允许 SQL 片段、函数调用、字符串、字段名直连和跨数据集引用。指标依赖会做循环检测，存在环时拒绝保存。

## 状态流转

指标状态：

- `draft`
- `active`
- `deprecated`
- `archived`

API 提供：

```text
POST /api/semantic-metrics/{metric}/activate
POST /api/semantic-metrics/{metric}/deprecate
POST /api/semantic-metrics/{metric}/archive
```

查询编译只允许选择 `active` 或 `deprecated` 指标。创建或更新复合指标公式时，依赖项必须是同数据集的 `active` 指标。

历史图表允许继续引用 `deprecated` 指标。`archived` 指标不会进入语义层选择和查询编译。

## 查询协议

原有查询协议仍可使用：

```json
{
  "dataset_id": 1,
  "dimensions": [
    {"field": "province"}
  ],
  "metrics": [
    {"field": "amount", "aggregate": "sum", "alias": "amount_sum"}
  ]
}
```

语义层查询新增：

```json
{
  "dataset_id": 1,
  "semantic_dimensions": [
    {"dimension_code": "province"}
  ],
  "semantic_metrics": [
    {"metric_code": "sales_amount"},
    {"metric_code": "avg_order_amount"}
  ],
  "limit": 100
}
```

编译规则：

- `semantic_dimensions` 会转换为原有 `dimensions`。
- 基础指标会转换为原有 `metrics`。
- 复合指标会递归展开依赖的基础指标，SQL 只查询依赖指标。
- 复合指标结果在 PHP 层按行计算。
- 依赖指标如果不是用户显式选择的可见指标，会从最终结果行中隐藏。

示例：

```text
sales_amount = sum(amount)
order_count = count(order_id)
avg_order_amount = sales_amount / order_count
```

请求 `sales_amount` 和 `avg_order_amount` 时，SQL 会查询 `sales_amount` 和 `order_count`，返回结果再追加 `avg_order_amount`，并隐藏仅作为依赖的 `order_count`。

## API

指标分类：

```text
GET    /api/metric-categories
POST   /api/metric-categories
GET    /api/metric-categories/{metricCategory}
PUT    /api/metric-categories/{metricCategory}
DELETE /api/metric-categories/{metricCategory}
```

指标：

```text
GET    /api/semantic-metrics
POST   /api/semantic-metrics
GET    /api/semantic-metrics/{metric}
PUT    /api/semantic-metrics/{metric}
DELETE /api/semantic-metrics/{metric}
POST   /api/semantic-metrics/validate-formula
GET    /api/semantic-metrics/{metric}/versions
GET    /api/semantic-metrics/{metric}/dependencies
GET    /api/semantic-metrics/{metric}/usages
GET    /api/semantic-metrics/{metric}/impact
```

维度：

```text
GET    /api/dimensions
POST   /api/dimensions
GET    /api/dimensions/{dimension}
PUT    /api/dimensions/{dimension}
DELETE /api/dimensions/{dimension}
GET    /api/datasets/{dataset}/dimensions
POST   /api/datasets/{dataset}/dimensions/init-from-fields
```

数据集语义层：

```text
GET  /api/datasets/{dataset}/metrics
POST /api/datasets/{dataset}/metrics/init-from-fields
GET  /api/datasets/{dataset}/semantic-layer
```

## 图表集成

图表配置支持新增字段：

```json
{
  "semantic_dimensions": [
    {"dimension_code": "province"}
  ],
  "semantic_metrics": [
    {"metric_code": "sales_amount"}
  ],
  "filters": [],
  "sorts": [],
  "limit": 100
}
```

图表保存和更新时会校验语义指标、维度是否可编译为合法查询，并同步 `metric_usages`。删除图表时会清理相关指标使用记录。

前端 `图表配置` 页提供数据集内语义维度和语义指标的快速选择。`语义层` 页提供：

- 指标分类管理。
- 指标管理和公式校验。
- 维度管理。
- 从数据集字段初始化指标和维度。
- 指标状态流转。
- 指标影响分析查看。

## 权限和安全

语义层管理接口在 `auth:sanctum` 下，并增加 `SemanticLayerAuthorizer`：

- `admin` 角色、`semantic.manage`、`metrics.manage` 或 `datasets.manage` 权限可以管理所有数据集下的指标和维度。
- 数据集创建者可以管理该数据集下的指标和维度。
- 通过 `resource_permissions` 显式授予 dataset `edit` / `manage` 的用户、角色、组织或部门，可以管理对应数据集下的指标和维度。
- 普通已登录用户只能查看自己有 dataset view 权限的 `active` 指标和 active 维度。
- 非管理用户不能查看 draft / archived 指标，不能编辑指标、维度，也不能调用公式校验接口。
- 指标版本、依赖、使用情况和影响分析需要先通过指标查看权限。

安全边界：

- 指标 code 全局唯一，只允许字母、数字、下划线。
- 维度 code 在同一数据集内唯一，只允许安全标识符。
- `source_field` 和维度 `field_name` 必须来自 `dataset_fields`。
- `aggregate_function` 使用白名单。
- 公式只解析指标 code、数字、四则运算和括号，不拼接任意 SQL。
- 保存指标依赖时检测循环依赖。
- 删除指标前检查下游指标依赖和图表使用记录。
- 查询日志只记录语义指标、维度、版本和 SQL 执行信息，不输出数据源连接密码等敏感配置。

## 缓存和日志

查询缓存 key 增加语义版本段：

```text
semantic:{metric_versions_hash|none}
```

这样同一个图表在指标版本变化后不会复用旧口径结果。

查询日志会记录：

- 是否使用语义层。
- 本次选择的语义指标。
- 本次选择的语义维度。
- 本次使用的指标版本映射。

查询日志页面支持按 `semantic_layer_used` 筛选。

## 影响分析

`GET /api/semantic-metrics/{metric}/impact` 返回：

- 使用该指标的图表数量和 ID。
- 这些图表所在仪表盘数量和 ID。
- 公开分享数量。
- 依赖该指标的下游指标。
- 风险级别：`low`、`medium`、`high`。

当前风险规则是基础版：

- 没有使用方为 `low`。
- 有图表使用为 `medium`。
- 影响仪表盘、公开分享或超过 5 个图表为 `high`。

## 边界

- 不实现完整审批流，只提供指标状态和版本记录。
- 不实现可视化公式编辑器，公式第一版用文本输入和白名单解析。
- 不支持 SQL 函数或任意表达式注入。
- 不支持跨数据集指标公式。
- 复合指标第一版在查询结果层按行计算，不下推为数据库表达式。
- 对复合指标排序暂不作为第一版能力；需要排序时建议使用可下推的基础指标或后续扩展查询编译器。
- 复杂血缘图谱、数据目录、自然语言指标生成和推荐算法不在本阶段范围内。

## 生产建议

- 核心指标需要业务负责人确认口径，并补全 owner、描述、单位和格式。
- 指标状态流转可以接入审批流；当前实现只提供状态和版本基础能力。
- 重要图表可在后续版本锁定具体 metric version，避免口径变更影响历史报表。
- 高频或慢指标可以结合 `query_logs` 和预聚合表做加速推荐。
- 指标权限建议继续和组织、角色、资源权限、行级权限、列级权限联动。
- 复杂企业场景可接入独立数据血缘或数据目录系统。

## 后续扩展

- 指标版本 diff 和回滚。
- 可视化公式编辑器。
- 复合指标 SQL 下推和复合指标排序。
- 指标审批流。
- dashboard / aggregate_definition 级 usage 记录。
- 跨数据集指标和复杂血缘图谱。
- 基于 query_logs 的指标热度、慢指标和预聚合推荐。
