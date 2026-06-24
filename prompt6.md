继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 11：BI 语义层 / 指标库 / 口径治理

## 一、当前项目背景

当前项目是 Laravel 13 + Docker 构建的 BI 分析工具。

前面阶段已经完成或计划完成：

1. 用户、角色、权限基础能力。
2. 数据源管理。
3. 数据集建模。
4. 查询引擎。
5. 图表管理。
6. 仪表盘管理。
7. 导入导出。
8. 查询缓存。
9. 数据权限。
10. 查询日志。
11. Prometheus metrics 和健康检查。
12. Vue 前端工程。
13. ClickHouse 查询加速。
14. ClickHouse 预聚合表。
15. 基于 query_logs 的加速推荐。
16. StarRocks / Doris 作为一等 OLAP 数据源接入。

当前系统已经具备“连接数据源、创建数据集、配置图表、查询展示”的主链路。

本阶段目标是增加企业 BI 系统中非常重要的一层：

```text
语义层 / 指标库 / 口径治理
```

也就是让业务人员和开发人员可以围绕统一指标口径进行分析，而不是每个图表都各自写一套指标逻辑。

## 二、本阶段目标

实现一套最小可用的 BI 语义层和指标库能力：

1. 支持统一管理业务指标。
2. 支持指标分类、指标口径、计算公式。
3. 支持基础指标、派生指标、复合指标。
4. 支持维度管理。
5. 支持指标和数据集字段绑定。
6. 支持图表选择指标库中的指标。
7. 支持查询引擎根据指标定义生成 SQL。
8. 支持指标版本管理。
9. 支持指标变更影响分析。
10. 支持指标血缘关系基础记录。
11. 支持指标审核状态。
12. 支持前端指标库管理页面。
13. 不破坏现有 dataset/chart/query 主流程。

## 三、本阶段明确不做

本阶段不要做：

1. 不做完整企业级数据治理平台。
2. 不做复杂元数据血缘图谱。
3. 不做可视化拖拽指标公式编辑器。
4. 不做自然语言生成指标。
5. 不做完整审批流引擎。
6. 不做跨系统数据目录。
7. 不重写现有查询引擎。
8. 不重写图表配置协议。
9. 不破坏已有 ClickHouse / StarRocks / Doris 查询逻辑。
10. 不做过度复杂的指标推荐算法。

本阶段只做：

```text
指标库最小闭环
+
语义层查询接入
+
版本和影响分析基础能力
```

## 四、核心概念

### 1. 数据源

真实数据库连接，例如：

```text
MySQL
PostgreSQL
ClickHouse
StarRocks
Doris
```

### 2. 数据集 Dataset

BI 中对物理表或 SQL 查询的封装。

例如：

```text
sales_orders 数据集
```

包含字段：

```text
order_date
province
city
amount
quantity
customer_id
department_id
```

### 3. 维度 Dimension

用于分组、筛选、下钻的字段。

例如：

```text
日期
省份
城市
部门
产品分类
客户类型
```

### 4. 指标 Metric

用于聚合统计的业务口径。

例如：

```text
销售额 = sum(amount)
订单数 = count(order_id)
客单价 = 销售额 / 订单数
转化率 = 成交客户数 / 访问客户数
```

### 5. 语义层 Semantic Layer

对数据集字段、维度、指标、计算口径进行统一管理。

图表不再直接写：

```text
sum(amount)
```

而是选择：

```text
销售额
```

查询引擎再根据指标定义生成 SQL。

## 五、数据库表设计

### 1. metric_categories

指标分类表。

字段建议：

```text
id
name
parent_id nullable
sort_order
description
created_at
updated_at
```

示例：

```text
销售类指标
客户类指标
产品类指标
财务类指标
运营类指标
```

### 2. metrics

指标主表。

字段建议：

```text
id
category_id nullable
dataset_id
name
code
description
metric_type
aggregate_function
source_field
formula
unit
precision
format_type
status
version
owner_id nullable
created_by nullable
updated_by nullable
created_at
updated_at
```

字段说明：

```text
metric_type:
- base 基础指标
- derived 派生指标
- compound 复合指标

aggregate_function:
- sum
- avg
- count
- countDistinct
- min
- max
- expression

source_field:
基础指标绑定的数据集字段

formula:
派生指标或复合指标公式

status:
- draft
- active
- deprecated
- archived
```

示例：

```text
销售额:
metric_type = base
aggregate_function = sum
source_field = amount

订单数:
metric_type = base
aggregate_function = count
source_field = order_id

客单价:
metric_type = compound
formula = sales_amount / order_count
```

### 3. metric_versions

指标版本表。

字段建议：

```text
id
metric_id
version
name
description
metric_type
aggregate_function
source_field
formula
unit
precision
format_type
status
change_summary
created_by nullable
created_at
```

用途：

1. 指标口径变更时保留历史。
2. 图表可以记录使用了哪个指标版本。
3. 便于追溯口径变化。

### 4. dimensions

维度表。

字段建议：

```text
id
dataset_id
name
code
field_name
dimension_type
time_grain_options_json
description
status
created_by nullable
created_at
updated_at
```

dimension_type：

```text
string
number
date
datetime
region
organization
user
enum
```

示例：

```text
订单日期
省份
城市
部门
产品分类
销售人员
```

### 5. metric_dependencies

指标依赖关系表。

字段建议：

```text
id
metric_id
depends_on_metric_id nullable
depends_on_field_name nullable
dependency_type
created_at
updated_at
```

dependency_type：

```text
metric
field
dimension
```

示例：

```text
客单价 依赖 销售额
客单价 依赖 订单数
销售额 依赖 amount 字段
```

### 6. metric_usages

指标使用记录表。

字段建议：

```text
id
metric_id
metric_version nullable
used_by_type
used_by_id
usage_context
created_at
updated_at
```

used_by_type：

```text
chart
dashboard
dataset
aggregate_definition
```

用途：

1. 记录指标被哪些图表使用。
2. 指标变更时做影响分析。
3. 指标废弃时提示受影响对象。

### 7. semantic_query_logs 可选

如果已有 query_logs 足够，可以不新建。

可以在 query_logs 增加字段：

```text
semantic_metrics_json
semantic_dimensions_json
semantic_layer_used
```

用于记录本次查询是否通过语义层生成。

## 六、服务类设计

建议新增目录：

```text
app/Services/Semantic/
```

核心服务：

```text
MetricService
MetricVersionService
DimensionService
MetricFormulaParser
MetricDependencyService
MetricUsageService
SemanticQueryCompiler
MetricImpactAnalysisService
```

### 1. MetricService

负责：

1. 指标 CRUD。
2. 指标启用 / 停用。
3. 指标分类。
4. 指标校验。
5. 指标和 dataset 绑定。
6. 查询 active 指标。
7. 根据 code 查找指标。
8. 生成指标版本。

### 2. MetricVersionService

负责：

1. 指标变更时创建版本。
2. 查询指标历史版本。
3. 回滚指标版本，可选。
4. 对比两个版本差异。

### 3. DimensionService

负责：

1. 维度 CRUD。
2. 从 dataset_fields 初始化维度。
3. 维度启用 / 停用。
4. 时间维度颗粒配置。
5. 维度字段校验。

### 4. MetricFormulaParser

负责解析派生指标公式。

第一版支持简单公式：

```text
sales_amount / order_count
sales_amount - refund_amount
sales_amount * 0.1
```

要求：

1. 公式中只能引用已存在指标 code。
2. 支持 + - * / 和括号。
3. 不允许任意 SQL 注入。
4. 不允许函数自由输入。
5. 解析失败返回清晰错误。
6. 第一版不做复杂表达式引擎。

### 5. MetricDependencyService

负责：

1. 分析指标依赖字段。
2. 分析指标依赖其他指标。
3. 保存 metric_dependencies。
4. 检查循环依赖。
5. 指标删除前检查依赖。
6. 数据集字段删除前检查影响。

循环依赖示例：

```text
A = B + 1
B = A + 1
```

必须禁止。

### 6. MetricUsageService

负责：

1. 图表使用指标时记录 metric_usages。
2. 仪表盘间接使用指标时可以通过图表追溯。
3. 指标变更时查询受影响图表。
4. 指标废弃时提示使用方。

### 7. SemanticQueryCompiler

负责把语义层配置转换成查询引擎可识别的 Query DTO。

输入示例：

```json
{
  "dataset_id": 1,
  "dimensions": ["order_month", "province"],
  "metrics": ["sales_amount", "order_count", "avg_order_amount"],
  "filters": [
    {"field": "order_date", "op": "between", "value": ["2026-01-01", "2026-12-31"]}
  ]
}
```

输出：

```text
dimensions -> dataset fields
metrics -> aggregate expressions
derived metrics -> expression
filters -> query filters
```

要求：

1. 基础指标生成聚合 SQL。
2. 复合指标基于基础指标二次计算。
3. 权限 filter 仍然由原有权限系统合并。
4. 字段名必须来自 dataset_fields。
5. 指标 code 必须来自 active metrics。
6. 不允许前端传任意 SQL 表达式。

### 8. MetricImpactAnalysisService

负责：

1. 查询某指标被哪些图表使用。
2. 查询某指标被哪些仪表盘间接使用。
3. 查询某字段变更会影响哪些指标。
4. 查询某数据集变更会影响哪些指标。
5. 返回影响范围。

影响分析结果示例：

```json
{
  "metric": "sales_amount",
  "used_by_charts": 8,
  "used_by_dashboards": 3,
  "dependent_metrics": ["avg_order_amount"],
  "risk_level": "medium"
}
```

## 七、查询引擎接入

当前系统图表查询可能是直接配置：

```text
metrics: [{ field: "amount", aggregate: "sum" }]
dimensions: ["province"]
```

本阶段新增语义层查询方式：

```text
metrics: ["sales_amount", "order_count"]
dimensions: ["province", "order_month"]
```

兼容原则：

1. 旧图表配置继续可用。
2. 新图表可以选择指标库指标。
3. QueryService 先判断是否使用 semantic metrics。
4. 如果使用语义层，则通过 SemanticQueryCompiler 转换。
5. 转换后继续走原有查询引擎。
6. ClickHouse / StarRocks / Doris 方言继续生效。
7. 查询缓存 key 需要包含 metric version。

缓存 key 建议增加：

```text
metric_versions_hash
```

示例：

```text
bi:chart:{chart_id}:user:{user_id}:semantic:{metric_versions_hash}:query:{query_hash}
```

## 八、图表配置改造

图表配置需要支持两种模式：

### 1. 原始字段模式

兼容已有逻辑：

```json
{
  "metrics": [
    {"field": "amount", "aggregate": "sum"}
  ]
}
```

### 2. 语义指标模式

新增：

```json
{
  "semantic_metrics": [
    {"metric_code": "sales_amount"},
    {"metric_code": "order_count"},
    {"metric_code": "avg_order_amount"}
  ],
  "semantic_dimensions": [
    {"dimension_code": "province"},
    {"dimension_code": "order_month"}
  ]
}
```

要求：

1. 旧图表不需要迁移也能查询。
2. 新图表可以选择指标。
3. 图表保存时记录 metric_usages。
4. 指标变更时可以找到受影响图表。

## 九、API 设计

新增接口前先检查当前 routes 风格。

### 1. 指标分类

```text
GET    /api/metric-categories
POST   /api/metric-categories
GET    /api/metric-categories/{category}
PUT    /api/metric-categories/{category}
DELETE /api/metric-categories/{category}
```

### 2. 指标管理

```text
GET    /api/metrics
POST   /api/metrics
GET    /api/metrics/{metric}
PUT    /api/metrics/{metric}
DELETE /api/metrics/{metric}

POST   /api/metrics/{metric}/activate
POST   /api/metrics/{metric}/deprecate
POST   /api/metrics/{metric}/archive

GET    /api/metrics/{metric}/versions
GET    /api/metrics/{metric}/dependencies
GET    /api/metrics/{metric}/usages
GET    /api/metrics/{metric}/impact
```

### 3. 维度管理

```text
GET    /api/dimensions
POST   /api/dimensions
GET    /api/dimensions/{dimension}
PUT    /api/dimensions/{dimension}
DELETE /api/dimensions/{dimension}

GET    /api/datasets/{dataset}/dimensions
POST   /api/datasets/{dataset}/dimensions/init-from-fields
```

### 4. 数据集指标

```text
GET    /api/datasets/{dataset}/metrics
POST   /api/datasets/{dataset}/metrics/init-from-fields
GET    /api/datasets/{dataset}/semantic-layer
```

### 5. 公式校验

```text
POST /api/metrics/validate-formula
```

请求示例：

```json
{
  "dataset_id": 1,
  "formula": "sales_amount / order_count"
}
```

返回：

```json
{
  "valid": true,
  "dependencies": ["sales_amount", "order_count"]
}
```

## 十、前端页面建议

如果 Vue 前端已存在，新增菜单：

```text
语义层
  - 指标分类
  - 指标库
  - 维度管理
  - 指标影响分析
```

### 1. 指标分类页面

功能：

1. 分类树。
2. 新增分类。
3. 编辑分类。
4. 删除分类。
5. 排序。

### 2. 指标库页面

功能：

1. 指标列表。
2. 按数据集筛选。
3. 按分类筛选。
4. 按状态筛选。
5. 新增指标。
6. 编辑指标。
7. 启用 / 废弃 / 归档。
8. 查看版本。
9. 查看依赖。
10. 查看使用情况。
11. 查看影响分析。

指标表单字段：

```text
名称
编码 code
分类
数据集
指标类型
聚合函数
来源字段
公式
单位
精度
格式
描述
状态
```

### 3. 维度管理页面

功能：

1. 维度列表。
2. 按数据集筛选。
3. 从字段初始化维度。
4. 编辑维度名称、编码、类型。
5. 设置时间颗粒。
6. 启用 / 停用。

### 4. 图表编辑页改造

图表配置中增加：

```text
选择指标
选择维度
```

要求：

1. 支持从指标库选择指标。
2. 支持从维度库选择维度。
3. 保留原来的字段选择方式。
4. 指标 hover 时展示口径说明。
5. 保存图表后记录指标使用关系。

## 十一、权限要求

语义层需要权限控制。

第一版权限规则：

1. 管理员可以管理所有指标。
2. 数据集负责人可以管理该数据集下指标。
3. 普通分析师可以查看 active 指标。
4. 普通用户不能编辑指标。
5. 废弃指标仍可被历史图表使用，但新图表不建议选择。
6. 归档指标不可被新图表选择。
7. 指标影响分析需要有指标查看权限。
8. 指标依赖的数据集无权限时，不允许查看明细字段。

## 十二、安全要求

1. 指标 code 必须唯一。
2. 指标 code 只允许字母、数字、下划线。
3. 公式不允许任意 SQL。
4. 公式只允许引用已有 active 指标。
5. 禁止循环依赖。
6. source_field 必须来自 dataset_fields。
7. aggregate_function 必须白名单。
8. 删除指标前检查使用情况。
9. 归档指标前提示影响范围。
10. 日志中不要输出敏感连接信息。

## 十三、初始化能力

为了方便演示，可以增加：

```text
从数据集字段初始化指标
从数据集字段初始化维度
```

### 1. 初始化维度规则

字段类型为：

```text
string
date
datetime
enum
region
organization
user
```

可以建议为维度。

### 2. 初始化指标规则

字段类型为：

```text
int
decimal
float
double
```

可以建议为 sum / avg 指标。

主键字段或 id 字段可以建议为 count 指标。

要求：

1. 只是生成草稿 draft。
2. 用户确认后 activate。
3. 不要自动覆盖已有指标。

## 十四、指标版本规则

指标每次重要字段变化时创建新版本。

重要字段包括：

```text
name
description
metric_type
aggregate_function
source_field
formula
unit
precision
format_type
status
```

版本号：

```text
1
2
3
...
```

要求：

1. 创建指标时生成 version 1。
2. 修改口径时生成新版本。
3. metric.version 指向当前版本。
4. 图表保存时记录使用的 metric_version。
5. 查询时默认使用当前 active 版本。
6. 历史图表是否锁定旧版本可以先预留。

## 十五、影响分析规则

指标变更前返回影响分析：

```text
影响图表数量
影响仪表盘数量
依赖当前指标的其他指标
是否存在 active 图表
是否存在公开分享仪表盘
```

风险等级：

```text
low：无人使用
medium：被少量图表使用
high：被多个仪表盘或公开分享使用
```

第一版规则：

```text
0 个使用 -> low
1-5 个图表使用 -> medium
超过 5 个图表或任意 dashboard 使用 -> high
```

## 十六、查询日志增强

如果当前 query_logs 支持 JSON 字段，增加记录：

```text
semantic_layer_used
semantic_metrics_json
semantic_dimensions_json
metric_versions_json
```

用途：

1. 后续分析哪些指标最常用。
2. 后续发现慢指标。
3. 后续推荐预聚合。
4. 后续做指标血缘分析。

## 十七、文档要求

新增：

```text
docs/bi-semantic-layer.md
```

内容包括：

1. 为什么需要语义层。
2. 指标库解决什么问题。
3. 指标、维度、数据集的关系。
4. 基础指标、派生指标、复合指标区别。
5. 指标版本机制。
6. 指标依赖关系。
7. 指标影响分析。
8. 图表如何使用指标。
9. 查询引擎如何编译指标。
10. 权限和安全边界。
11. 当前实现限制。
12. 后续扩展方向。

生产环境建议：

1. 指标口径需要业务负责人确认。
2. 指标变更需要审批。
3. 指标需要有负责人和描述。
4. 重要指标需要版本锁定。
5. 指标需要和数据血缘系统结合。
6. 指标慢查询可以结合 query_logs 做优化。
7. 高频指标可以结合预聚合表加速。
8. 指标权限需要和组织、数据权限结合。

## 十八、验收标准

完成后需要满足：

1. 可以创建指标分类。
2. 可以创建基础指标。
3. 可以创建复合指标。
4. 可以创建维度。
5. 可以从数据集字段初始化维度。
6. 可以从数据集字段初始化指标草稿。
7. 指标公式可以校验。
8. 循环依赖会被拒绝。
9. 指标变更会创建版本。
10. 可以查看指标版本。
11. 可以查看指标依赖。
12. 可以查看指标使用情况。
13. 可以查看指标影响分析。
14. 图表可以选择语义指标。
15. 查询引擎可以根据语义指标生成 SQL。
16. 原有字段模式图表继续可用。
17. query_logs 可以记录语义层使用情况。
18. 权限校验生效。
19. migration 可以正常执行和回滚。
20. 尽量通过 php artisan route:list。
21. 尽量通过 php artisan test。
22. 如果有前端，尽量通过 npm run build。

## 十九、运行命令

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

如果有代码格式化：

```text
composer format
npm run lint
```

## 二十、代码要求

1. 不要重写现有查询引擎。
2. 语义层要作为增强能力接入。
3. 旧图表配置必须兼容。
4. 指标字段必须来自 dataset_fields。
5. 指标公式不能直接拼 SQL。
6. 指标 code 必须唯一且安全。
7. 指标依赖不能循环。
8. 指标变更必须保留版本。
9. 图表使用指标要记录 usage。
10. migration 必须可回滚。
11. 文档必须说明边界。
12. 前端页面可以简洁，但链路要完整。

## 二十一、当前阶段不做内容

本阶段不做：

1. 完整审批流。
2. 复杂血缘图谱可视化。
3. 自然语言指标生成。
4. 指标智能推荐。
5. 跨数据集复杂指标。
6. 指标权限继承复杂模型。
7. 商业级数据目录。
8. 多租户指标隔离完整方案。

这些可以作为后续扩展方向写入文档。

## 二十二、最终输出要求

任务完成后，请输出：

1. 本次完成内容。
2. 修改文件列表。
3. 新增 migration 列表。
4. 新增 Model 列表。
5. 新增 Service 列表。
6. 新增 API 列表。
7. 新增前端页面列表。
8. 指标库设计说明。
9. 维度管理设计说明。
10. 指标公式解析说明。
11. 指标版本机制说明。
12. 指标影响分析说明。
13. 查询引擎接入说明。
14. query_logs 增强说明。
15. 运行过的命令。
16. 测试结果。
17. 当前实现边界。
18. 下一阶段建议。