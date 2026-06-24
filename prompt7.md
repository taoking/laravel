继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 12：元数据目录 / 数据血缘 / 影响分析增强

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
11. Vue 前端工程。
12. ClickHouse 查询加速。
13. ClickHouse 预聚合表。
14. 基于 query_logs 的加速推荐。
15. StarRocks / Doris 作为一等 OLAP 数据源接入。
16. BI 语义层 / 指标库 / 口径治理。

当前系统已经具备：

```text
数据源 -> 数据集 -> 维度/指标 -> 查询引擎 -> 图表 -> 仪表盘 -> 查询日志 -> 查询加速
```

本阶段目标是补齐企业 BI 平台中非常重要的治理能力：

```text
元数据目录 / 数据血缘 / 影响分析
```

也就是让系统可以回答这些问题：

1. 某个字段被哪些数据集使用？
2. 某个指标依赖哪些字段和其他指标？
3. 某个图表依赖哪些数据集和指标？
4. 某个仪表盘包含哪些图表和指标？
5. 删除或修改字段前，会影响哪些图表和仪表盘？
6. 某个数据源下有哪些表、字段、数据集、指标和图表？
7. 哪些数据集长期无人使用？
8. 哪些指标被大量使用，属于核心指标？
9. 哪些图表查询慢，是否和某些字段、指标有关？

## 二、本阶段目标

实现一套最小可用的数据治理和血缘分析能力：

1. 建立统一元数据目录。
2. 记录数据源、表、字段、数据集、维度、指标、图表、仪表盘之间的关系。
3. 支持字段级血缘。
4. 支持指标级血缘。
5. 支持图表级血缘。
6. 支持仪表盘级血缘。
7. 支持数据变更影响分析。
8. 支持删除前风险检查。
9. 支持元数据搜索。
10. 支持资产使用热度统计。
11. 支持前端元数据目录页面。
12. 支持前端血缘查看页面。
13. 支持文档说明当前边界。

## 三、本阶段明确不做

本阶段不要做：

1. 不做完整企业级数据治理平台。
2. 不做复杂图数据库。
3. 不强制接 Neo4j。
4. 不做复杂可视化血缘图编辑器。
5. 不做自动 SQL AST 完整解析器。
6. 不做跨系统全链路血缘。
7. 不做 Kafka / Flink / ETL 作业血缘。
8. 不做复杂数据目录审批流。
9. 不重写现有数据集、指标、图表逻辑。
10. 不破坏现有查询链路。

本阶段只做：

```text
基于当前 BI 系统内部元数据的血缘关系记录、查询和影响分析
```

## 四、核心概念

### 1. 元数据资产 Metadata Asset

系统内所有可被治理的对象都可以看作资产：

```text
data_source
physical_table
physical_column
dataset
dataset_field
dimension
metric
chart
dashboard
aggregate_table
materialized_view
```

### 2. 血缘关系 Lineage Relation

资产之间的依赖关系：

```text
dataset depends_on physical_table
dataset_field maps_to physical_column
metric depends_on dataset_field
metric depends_on metric
chart uses dataset
chart uses metric
dashboard contains chart
aggregate_table built_from dataset
```

### 3. 影响分析 Impact Analysis

当一个资产发生变化时，分析它会影响哪些下游对象。

示例：

```text
amount 字段变更
    ↓
销售额指标受影响
    ↓
客单价指标受影响
    ↓
销售趋势图受影响
    ↓
销售分析仪表盘受影响
```

## 五、数据库表设计

### 1. metadata_assets

统一元数据资产表。

字段建议：

```text
id
asset_type
asset_id
name
code nullable
description nullable
data_source_id nullable
dataset_id nullable
status
owner_id nullable
tags_json nullable
properties_json nullable
last_synced_at nullable
created_at
updated_at
```

asset_type 示例：

```text
data_source
physical_table
physical_column
dataset
dataset_field
dimension
metric
chart
dashboard
acceleration_profile
aggregate_definition
materialized_view
```

说明：

1. asset_type + asset_id 唯一。
2. properties_json 保存不同资产的扩展信息。
3. tags_json 用于元数据标签。
4. status 可为 active / deprecated / archived。

### 2. metadata_lineage_relations

统一血缘关系表。

字段建议：

```text
id
source_asset_type
source_asset_id
target_asset_type
target_asset_id
relation_type
relation_detail_json nullable
confidence
created_by_system
created_at
updated_at
```

relation_type 示例：

```text
depends_on
contains
uses
maps_to
built_from
derived_from
generated_by
```

示例：

```text
dataset -> depends_on -> physical_table
dataset_field -> maps_to -> physical_column
metric -> depends_on -> dataset_field
compound_metric -> depends_on -> base_metric
chart -> uses -> metric
dashboard -> contains -> chart
aggregate_definition -> built_from -> dataset
```

confidence：

```text
high
medium
low
```

第一版系统自动生成的关系都可以是 high。

### 3. metadata_tags

元数据标签表。

字段建议：

```text
id
name
color nullable
description nullable
created_at
updated_at
```

示例标签：

```text
核心指标
财务数据
敏感字段
高频使用
低频使用
待废弃
生产数据
测试数据
```

### 4. metadata_asset_tags

资产标签关联表。

字段建议：

```text
id
asset_type
asset_id
tag_id
created_at
updated_at
```

### 5. metadata_usage_stats

资产使用统计表。

字段建议：

```text
id
asset_type
asset_id
usage_date
query_count
view_count
edit_count
last_used_at nullable
avg_duration_ms nullable
slow_query_count
created_at
updated_at
```

说明：

1. 可从 query_logs、chart views、dashboard views 聚合生成。
2. 第一版可以通过 Artisan 命令生成。
3. 也可以通过 API 实时聚合 query_logs，不强制落表。

### 6. impact_analysis_logs

影响分析记录表，可选。

字段建议：

```text
id
asset_type
asset_id
change_type
impact_result_json
risk_level
analyzed_by nullable
created_at
updated_at
```

change_type：

```text
delete
update
deprecate
archive
schema_change
```

risk_level：

```text
low
medium
high
critical
```

## 六、服务类设计

建议新增目录：

```text
app/Services/Metadata/
```

核心服务：

```text
MetadataAssetService
MetadataLineageService
MetadataSyncService
MetadataSearchService
MetadataImpactAnalysisService
MetadataUsageStatService
MetadataTagService
MetadataGraphService
```

### 1. MetadataAssetService

负责：

1. 注册元数据资产。
2. 更新元数据资产。
3. 删除或归档资产。
4. 根据 asset_type + asset_id 查找资产。
5. 同步数据源、数据集、指标、图表、仪表盘到 metadata_assets。
6. 维护资产状态。
7. 维护资产扩展属性。

### 2. MetadataLineageService

负责：

1. 创建血缘关系。
2. 删除旧血缘关系。
3. 重建某个资产的血缘关系。
4. 查询上游血缘。
5. 查询下游血缘。
6. 查询完整链路。
7. 防止重复关系。
8. 支持按 relation_type 过滤。

建议方法：

```php
syncDatasetLineage(int $datasetId): void

syncMetricLineage(int $metricId): void

syncChartLineage(int $chartId): void

syncDashboardLineage(int $dashboardId): void

getUpstream(string $assetType, int $assetId, int $depth = 3): array

getDownstream(string $assetType, int $assetId, int $depth = 3): array
```

### 3. MetadataSyncService

负责全量或增量同步元数据。

同步范围：

1. data_sources。
2. data_source_tables。
3. data_source_fields。
4. datasets。
5. dataset_fields。
6. dimensions。
7. metrics。
8. charts。
9. dashboards。
10. acceleration_profiles。
11. aggregate_definitions。
12. materialized_views，如果已有。

建议新增 Artisan 命令：

```text
php artisan bi:metadata:sync
```

支持参数：

```text
--all
--data-source=
--dataset=
--metrics
--charts
--dashboards
--dry-run
```

### 4. MetadataSearchService

负责元数据搜索。

支持搜索：

1. 资产名称。
2. 资产 code。
3. 描述。
4. 标签。
5. 字段名。
6. 指标名。
7. 数据集名。
8. 图表名。
9. 仪表盘名。

第一版可以直接用 MySQL like 查询。
后续可扩展 Elasticsearch / Meilisearch。

### 5. MetadataImpactAnalysisService

负责影响分析。

输入：

```text
asset_type
asset_id
change_type
```

输出：

```text
affected_datasets
affected_metrics
affected_charts
affected_dashboards
affected_acceleration_profiles
risk_level
suggestions
```

风险等级规则第一版：

```text
low:
  无下游对象

medium:
  影响 1-3 个图表或 1 个仪表盘

high:
  影响 4-10 个图表或多个仪表盘

critical:
  影响核心指标、公开分享仪表盘、或超过 10 个图表
```

### 6. MetadataUsageStatService

负责资产使用统计。

数据来源：

1. query_logs。
2. chart query logs。
3. dashboard view logs，如果已有。
4. export logs。
5. import logs。
6. acceleration query logs。

统计内容：

1. 图表查询次数。
2. 仪表盘访问次数。
3. 指标使用次数。
4. 数据集查询次数。
5. 慢查询次数。
6. 平均查询耗时。
7. 最近使用时间。
8. 是否长期未使用。

建议新增 Artisan 命令：

```text
php artisan bi:metadata:usage-stats
```

### 7. MetadataTagService

负责：

1. 标签 CRUD。
2. 给资产打标签。
3. 移除标签。
4. 查询某标签下资产。
5. 自动标签规则预留。

自动标签第一版可选：

```text
慢查询高频 -> 高频慢查询
30 天无查询 -> 低频使用
被多个 dashboard 使用 -> 核心资产
字段名包含 phone/mobile/id_card -> 疑似敏感字段
```

### 8. MetadataGraphService

负责将血缘关系转换成前端可视化结构。

输出格式：

```json
{
  "nodes": [
    {
      "id": "metric:1",
      "type": "metric",
      "name": "销售额"
    }
  ],
  "edges": [
    {
      "source": "dataset_field:3",
      "target": "metric:1",
      "relation": "depends_on"
    }
  ]
}
```

## 七、血缘同步规则

### 1. 数据源血缘

```text
data_source -> contains -> physical_table
physical_table -> contains -> physical_column
```

### 2. 数据集血缘

```text
dataset -> depends_on -> physical_table
dataset_field -> maps_to -> physical_column
dataset -> contains -> dataset_field
```

如果数据集是 SQL 数据集，第一版可以记录：

```text
dataset -> depends_on -> data_source
```

暂不做复杂 SQL AST 字段解析。

### 3. 维度血缘

```text
dimension -> depends_on -> dataset_field
```

### 4. 指标血缘

基础指标：

```text
metric -> depends_on -> dataset_field
```

复合指标：

```text
compound_metric -> depends_on -> base_metric
```

派生指标：

```text
derived_metric -> depends_on -> metric
derived_metric -> depends_on -> dataset_field
```

### 5. 图表血缘

```text
chart -> uses -> dataset
chart -> uses -> metric
chart -> uses -> dimension
chart -> uses -> dataset_field
```

旧图表如果没有使用 semantic metric，则记录：

```text
chart -> uses -> dataset_field
```

### 6. 仪表盘血缘

```text
dashboard -> contains -> chart
dashboard -> uses -> dataset
dashboard -> uses -> metric
```

仪表盘使用 dataset / metric 可以通过 chart 间接推导，也可以冗余记录，方便查询。

### 7. 加速表血缘

```text
acceleration_profile -> built_from -> dataset
aggregate_definition -> built_from -> acceleration_profile
aggregate_definition -> uses -> metric
aggregate_definition -> uses -> dimension
```

## 八、API 设计

新增接口前先检查当前 routes 风格。

### 1. 元数据资产

```text
GET    /api/metadata/assets
GET    /api/metadata/assets/{assetType}/{assetId}
PUT    /api/metadata/assets/{assetType}/{assetId}
POST   /api/metadata/assets/{assetType}/{assetId}/archive
```

查询参数：

```text
asset_type
keyword
tag
status
owner_id
data_source_id
dataset_id
```

### 2. 元数据搜索

```text
GET /api/metadata/search
```

参数：

```text
keyword
asset_type
tag
limit
```

### 3. 血缘查询

```text
GET /api/metadata/lineage/{assetType}/{assetId}/upstream
GET /api/metadata/lineage/{assetType}/{assetId}/downstream
GET /api/metadata/lineage/{assetType}/{assetId}/graph
POST /api/metadata/lineage/{assetType}/{assetId}/sync
```

### 4. 影响分析

```text
POST /api/metadata/impact/analyze
```

请求示例：

```json
{
  "asset_type": "dataset_field",
  "asset_id": 12,
  "change_type": "delete"
}
```

返回示例：

```json
{
  "risk_level": "high",
  "affected_metrics": [],
  "affected_charts": [],
  "affected_dashboards": [],
  "suggestions": [
    "该字段被多个核心图表使用，删除前建议先替换字段或下线相关图表。"
  ]
}
```

### 5. 元数据标签

```text
GET    /api/metadata/tags
POST   /api/metadata/tags
PUT    /api/metadata/tags/{tag}
DELETE /api/metadata/tags/{tag}

POST   /api/metadata/assets/{assetType}/{assetId}/tags
DELETE /api/metadata/assets/{assetType}/{assetId}/tags/{tag}
```

### 6. 使用统计

```text
GET /api/metadata/usage-stats
GET /api/metadata/assets/{assetType}/{assetId}/usage-stats
```

### 7. 同步任务

```text
POST /api/metadata/sync
```

请求示例：

```json
{
  "scope": "dataset",
  "dataset_id": 1
}
```

## 九、前端页面建议

如果 Vue 前端已经存在，新增菜单：

```text
数据治理
  - 元数据目录
  - 数据血缘
  - 影响分析
  - 资产标签
  - 使用统计
```

### 1. 元数据目录页面

功能：

1. 资产列表。
2. 按资产类型筛选。
3. 按标签筛选。
4. 按数据源筛选。
5. 按数据集筛选。
6. 关键字搜索。
7. 查看资产详情。
8. 查看上游血缘。
9. 查看下游血缘。
10. 查看使用统计。
11. 查看标签。
12. 编辑描述和负责人。

资产类型展示：

```text
数据源
物理表
物理字段
数据集
数据集字段
维度
指标
图表
仪表盘
加速配置
预聚合表
物化视图
```

### 2. 数据血缘页面

功能：

1. 选择资产。
2. 查看上游。
3. 查看下游。
4. 查看图谱。
5. 按深度展开。
6. 按资产类型过滤。
7. 点击节点查看详情。

图谱第一版可以简单展示：

1. 节点列表。
2. 边列表。
3. 层级树。
4. 不强制复杂可视化库。

如果已有前端图形库，可以用简单 DAG。
如果没有，先用表格和树形结构。

### 3. 影响分析页面

功能：

1. 选择资产类型。
2. 选择资产。
3. 选择变更类型。
4. 点击分析。
5. 展示风险等级。
6. 展示受影响指标。
7. 展示受影响图表。
8. 展示受影响仪表盘。
9. 展示建议处理方式。

### 4. 资产标签页面

功能：

1. 标签列表。
2. 新增标签。
3. 编辑标签。
4. 删除标签。
5. 给资产打标签。

### 5. 使用统计页面

功能：

1. 高频使用资产。
2. 低频使用资产。
3. 慢查询相关资产。
4. 核心指标。
5. 核心仪表盘。
6. 最近未使用数据集。

## 十、删除前风险检查

为关键对象删除增加影响分析检查。

对象包括：

1. data_source。
2. dataset。
3. dataset_field。
4. metric。
5. dimension。
6. chart。
7. dashboard。

删除前流程：

```text
用户请求删除
    ↓
调用 MetadataImpactAnalysisService
    ↓
如果 risk_level 为 high / critical
    ↓
返回确认提示和影响列表
    ↓
需要 force=true 才允许继续删除
```

第一版可以只在 API 层返回影响分析，不强制拦截所有删除。
但文档中需要说明后续可以接入强制拦截。

## 十一、Artisan 命令

新增：

```text
php artisan bi:metadata:sync
php artisan bi:metadata:lineage:rebuild
php artisan bi:metadata:usage-stats
```

### 1. bi:metadata:sync

用途：

```text
同步系统已有对象到 metadata_assets
```

参数：

```text
--all
--data-source=
--dataset=
--dry-run
```

### 2. bi:metadata:lineage:rebuild

用途：

```text
重建血缘关系
```

参数：

```text
--all
--dataset=
--metric=
--chart=
--dashboard=
--dry-run
```

### 3. bi:metadata:usage-stats

用途：

```text
基于 query_logs 生成资产使用统计
```

参数：

```text
--date=
--days=1
--dry-run
```

## 十二、权限要求

1. 普通用户可以查看自己有权限的数据集、图表、仪表盘的元数据。
2. 管理员可以查看所有元数据。
3. 数据源密码、连接信息不能在元数据详情中泄露。
4. 敏感字段标签只允许管理员维护。
5. 影响分析结果需要过滤无权限资产。
6. 元数据同步命令需要管理员权限或 CLI。
7. 标签管理需要管理员或数据治理权限。
8. 删除前影响分析不应该暴露无权限对象的详细信息。

## 十三、安全要求

1. 不输出数据库密码。
2. 不暴露完整连接字符串。
3. asset_type 必须白名单。
4. relation_type 必须白名单。
5. metadata search 不允许任意 SQL。
6. 图谱查询要限制最大深度。
7. API 要限制分页大小。
8. impact analysis 要避免递归死循环。
9. 血缘关系重建要避免重复数据。
10. 删除资产前要检查下游依赖。

## 十四、使用统计规则

基于 query_logs 生成：

### 1. 数据集使用

```text
dataset query count
avg duration
slow query count
last used at
```

### 2. 指标使用

从 query_logs.semantic_metrics_json 或 chart config 中统计。

```text
metric usage count
chart count
dashboard count
last used at
```

### 3. 图表使用

```text
chart query count
avg duration
cache hit count
acceleration hit count
last queried at
```

### 4. 仪表盘使用

如果没有 dashboard view logs，可以通过 dashboard 内 chart query_logs 间接估算。

### 5. 低频资产

规则：

```text
30 天内无 query_logs
或 query_count = 0
```

### 6. 高频慢查询资产

规则：

```text
query_count >= 10
avg_duration_ms >= 3000
```

## 十五、文档要求

新增：

```text
docs/bi-metadata-lineage.md
```

内容包括：

1. 为什么 BI 系统需要元数据目录。
2. 为什么需要数据血缘。
3. 当前系统资产类型。
4. metadata_assets 表说明。
5. metadata_lineage_relations 表说明。
6. 数据源、数据集、字段、指标、图表、仪表盘关系。
7. 血缘同步规则。
8. 影响分析流程。
9. 删除前风险检查。
10. 使用统计规则。
11. 标签体系说明。
12. 当前实现边界。
13. 后续扩展方向。

生产环境建议：

1. 复杂 SQL 字段血缘可以接 SQL Parser。
2. 全链路血缘可以接调度系统、ETL 系统和数据仓库元数据。
3. 大型图谱可以用 Neo4j 或图数据库。
4. 元数据搜索可以接 Elasticsearch / Meilisearch。
5. 数据资产负责人需要配合组织权限。
6. 敏感字段需要结合数据脱敏和审计。
7. 高风险变更需要审批流。
8. 核心指标变更需要版本和通知机制。

## 十六、验收标准

完成后需要满足：

1. 可以同步 metadata_assets。
2. 可以同步 data_source / dataset / metric / chart / dashboard 资产。
3. 可以建立 dataset 到 physical table 的血缘。
4. 可以建立 metric 到 dataset_field 的血缘。
5. 可以建立 compound metric 到 base metric 的血缘。
6. 可以建立 chart 到 metric / dataset 的血缘。
7. 可以建立 dashboard 到 chart 的血缘。
8. 可以查询上游血缘。
9. 可以查询下游血缘。
10. 可以查询血缘图谱结构。
11. 可以执行影响分析。
12. 可以识别高风险变更。
13. 可以给资产打标签。
14. 可以搜索元数据资产。
15. 可以统计资产使用情况。
16. 可以识别低频资产和高频慢查询资产。
17. 权限过滤生效。
18. 不暴露敏感连接信息。
19. 不破坏现有 BI 查询流程。
20. migration 可以正常执行和回滚。
21. 尽量通过 php artisan route:list。
22. 尽量通过 php artisan test。
23. 如果有前端，尽量通过 npm run build。

## 十七、运行命令

尽量运行：

```text
php artisan migrate
php artisan route:list
php artisan test
php artisan bi:metadata:sync --all --dry-run
php artisan bi:metadata:lineage:rebuild --all --dry-run
php artisan bi:metadata:usage-stats --dry-run
```

如果有前端：

```text
cd frontend
npm run build
```

## 十八、代码要求

1. 不要重写现有查询引擎。
2. 元数据目录作为增强能力接入。
3. 血缘关系尽量由已有业务对象自动生成。
4. asset_type 必须白名单。
5. relation_type 必须白名单。
6. 避免递归死循环。
7. 图谱查询要限制深度。
8. 元数据搜索要分页。
9. 删除前影响分析要可复用。
10. migration 必须可回滚。
11. 文档必须说明边界。
12. 前端页面可以简洁，但链路要完整。

## 十九、当前阶段不做内容

本阶段不做：

1. Neo4j 图数据库。
2. 完整 SQL AST 字段级解析。
3. 跨系统 ETL 血缘。
4. Kafka / Flink 作业血缘。
5. 复杂审批流。
6. 数据质量规则引擎。
7. 敏感数据自动识别完整模型。
8. 商业级数据资产平台。

这些作为后续扩展写入文档。

## 二十、最终输出要求

任务完成后，请输出到文档：

1. 本次完成内容。
2. 修改文件列表。
3. 新增 migration 列表。
4. 新增 Model 列表。
5. 新增 Service 列表。
6. 新增 API 列表。
7. 新增 Artisan Command 列表。
8. 新增前端页面列表。
9. 元数据资产设计说明。
10. 血缘关系设计说明。
11. 影响分析流程说明。
12. 使用统计规则说明。
13. 标签体系说明。
14. 权限和安全边界说明。
15. 新增文档位置。
16. 运行过的命令。
17. 测试结果。
18. 当前实现边界。
19. 下一阶段建议。