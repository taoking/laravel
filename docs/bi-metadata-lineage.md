# BI 元数据目录 / 数据血缘 / 影响分析

本文档对应 Phase 12。当前实现是在已有数据源、数据集、语义层、图表、仪表盘、查询日志和查询加速能力之上，补齐最小可用的数据治理能力，让系统能回答“字段被谁使用”“指标依赖什么”“删除资产会影响哪些图表和仪表盘”等问题。

## 为什么需要元数据目录

BI 系统里的数据源、表、字段、数据集、指标、图表和仪表盘本质上都是可治理资产。没有统一目录时，用户只能在各个功能页分散查找对象，无法快速判断资产归属、状态、标签、使用热度和下游影响。

本阶段新增统一资产表 `metadata_assets`，将内部对象同步为可搜索、可打标签、可做血缘和影响分析的资产。

## 为什么需要数据血缘

数据血缘用于记录资产之间的依赖关系。当前系统的主链路是：

```text
data_source -> physical_table -> physical_column
dataset -> dataset_field
dimension / metric -> dataset_field
chart -> dataset / dimension / metric / dataset_field
dashboard -> chart / dataset / metric
acceleration_profile / aggregate_definition -> dataset / dataset_field
```

有了血缘后，字段删除、指标口径调整、数据集下线等操作都可以先查询下游对象，再决定是否继续。

## 当前资产类型

`metadata_assets.asset_type` 使用白名单：

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

`asset_type + asset_id` 唯一。不同类型可以复用同一个业务 ID。

## metadata_assets

核心字段：

- `asset_type` / `asset_id`：内部资产类型和业务表 ID。
- `name` / `code` / `description`：展示与搜索字段。
- `data_source_id` / `dataset_id`：便于按数据源、数据集过滤和做权限判断。
- `status`：`active`、`deprecated`、`archived`、`disabled` 等。
- `owner_id`：资产负责人。
- `tags_json`：轻量扩展标签字段。
- `properties_json`：类型相关扩展信息。
- `last_synced_at`：最近同步时间。

数据源资产不会写入 host、username、password、token、secret、connection string 等敏感连接信息。

## metadata_lineage_relations

血缘关系使用统一关系表：

- `source_asset_type` / `source_asset_id`：关系来源。
- `target_asset_type` / `target_asset_id`：关系目标。
- `relation_type`：关系类型。
- `relation_detail_json`：字段名、指标编码、公式等补充信息。
- `confidence`：`high`、`medium`、`low`。
- `created_by_system`：是否系统生成。

`relation_type` 使用白名单：

```text
depends_on
contains
uses
maps_to
built_from
derived_from
generated_by
```

关系方向约定为“来源资产依赖或包含目标资产”。例如：

```text
metric -> depends_on -> dataset_field
chart -> uses -> metric
dashboard -> contains -> chart
```

查询上游时沿 source -> target 方向走；查询下游时沿 target <- source 方向反查。图谱深度最大限制为 5，遍历时用 visited key 防止递归死循环。

## 同步规则

`MetadataSyncService` 负责把已有业务对象注册为 `metadata_assets`，并触发血缘重建。

资产同步范围：

- `data_sources`
- `data_source_tables`
- `data_source_fields`
- `datasets`
- `dataset_fields`
- `dimensions`
- `metrics`
- `charts`
- `dashboards`
- `acceleration_profiles`
- `acceleration_aggregate_definitions`
- `data_source_tables.table_type` 包含 materialized 时同步为 `materialized_view`

血缘同步规则：

- `data_source contains physical_table`
- `physical_table contains physical_column`
- `dataset depends_on physical_table`
- `dataset contains dataset_field`
- `dataset_field maps_to physical_column`
- SQL 数据集或找不到物理表时，第一版降级记录 `dataset depends_on data_source`
- `dimension depends_on dataset_field`
- 基础指标 `metric depends_on dataset_field`
- 复合/派生指标 `metric depends_on metric`
- 图表 `chart uses dataset / metric / dimension / dataset_field`
- 仪表盘 `dashboard contains chart`，并冗余记录 `dashboard uses dataset / metric`
- 加速配置 `acceleration_profile built_from dataset`
- 预聚合定义 `aggregate_definition built_from dataset / acceleration_profile`，并记录使用字段

第一版不做完整 SQL AST 字段解析。

## API

元数据资产：

```text
GET    /api/metadata/assets
GET    /api/metadata/assets/{assetType}/{assetId}
PUT    /api/metadata/assets/{assetType}/{assetId}
POST   /api/metadata/assets/{assetType}/{assetId}/archive
```

搜索：

```text
GET /api/metadata/search
```

血缘：

```text
GET  /api/metadata/lineage/{assetType}/{assetId}/upstream
GET  /api/metadata/lineage/{assetType}/{assetId}/downstream
GET  /api/metadata/lineage/{assetType}/{assetId}/graph
POST /api/metadata/lineage/{assetType}/{assetId}/sync
```

影响分析：

```text
POST /api/metadata/impact/analyze
```

标签：

```text
GET    /api/metadata/tags
POST   /api/metadata/tags
PUT    /api/metadata/tags/{tag}
DELETE /api/metadata/tags/{tag}
POST   /api/metadata/assets/{assetType}/{assetId}/tags
DELETE /api/metadata/assets/{assetType}/{assetId}/tags/{tag}
```

使用统计：

```text
GET /api/metadata/usage-stats
GET /api/metadata/assets/{assetType}/{assetId}/usage-stats
```

同步：

```text
POST /api/metadata/sync
```

所有接口在 `auth:sanctum` 下。分页大小最大 100。

## Artisan 命令

```text
php artisan bi:metadata:sync --all --dry-run
php artisan bi:metadata:sync --data-source=1
php artisan bi:metadata:sync --dataset=1
php artisan bi:metadata:sync --metrics
php artisan bi:metadata:sync --charts
php artisan bi:metadata:sync --dashboards

php artisan bi:metadata:lineage:rebuild --all --dry-run
php artisan bi:metadata:lineage:rebuild --dataset=1
php artisan bi:metadata:lineage:rebuild --metric=1
php artisan bi:metadata:lineage:rebuild --chart=1
php artisan bi:metadata:lineage:rebuild --dashboard=1

php artisan bi:metadata:usage-stats --date=2026-06-24 --days=1 --dry-run
```

CLI 命令默认允许执行；API 同步入口需要管理员或治理管理权限。

## 影响分析流程

输入：

```json
{
  "asset_type": "dataset_field",
  "asset_id": 12,
  "change_type": "delete"
}
```

流程：

1. 校验资产类型白名单，并检查当前用户是否可见该资产。
2. 查询 5 层下游血缘。
3. 按资产类型聚合受影响的数据集、字段、维度、指标、图表、仪表盘和加速配置。
4. 过滤当前用户无权查看的资产。
5. 检查公开分享仪表盘和核心指标标签。
6. 根据规则计算风险等级。
7. 写入 `impact_analysis_logs`。

风险等级：

- `low`：无可见下游对象。
- `medium`：影响 1-3 个图表或 1 个仪表盘。
- `high`：影响 4-10 个图表或多个仪表盘。
- `critical`：影响核心指标、公开分享仪表盘，或超过 10 个图表。

第一版删除前不强制拦截业务删除接口，只提供可复用影响分析 API。后续可在 data source、dataset、dataset field、metric、dimension、chart、dashboard 删除入口接入 `force=true` 二次确认。

## 使用统计

`MetadataUsageStatService` 从 `query_logs` 聚合生成 `metadata_usage_stats`：

- 数据集：按 `dataset_id` 统计查询次数、慢查询、平均耗时、最近使用时间。
- 图表：按 `chart_id` 统计查询次数和耗时。
- 仪表盘：优先使用 `dashboard_id`，也会通过 dashboard widget 从图表查询日志间接估算。
- 指标：从 `semantic_metrics_json` 读取指标编码；如果日志未记录，则从图表 config 的 `semantic_metrics` 兜底。
- 加速配置：按 `acceleration_profile_id` 和 `aggregate_definition_id` 统计。

低频资产规则：

```text
最近 30 天无 query_count > 0 的 usage stat
```

高频慢查询规则：

```text
query_count >= 10
avg_duration_ms >= 3000
```

## 标签体系

`metadata_tags` 维护标签基础信息，`metadata_asset_tags` 维护资产标签关联。

建议标签：

- 核心指标
- 财务数据
- 敏感字段
- 高频使用
- 低频使用
- 待废弃
- 生产数据
- 测试数据

普通标签需要管理员或治理管理权限维护。包含 `敏感`、`sensitive`、`phone`、`id_card` 的敏感标签只允许管理员维护。

## 前端页面

新增 `resources/js/pages/DataGovernanceView.vue`，左侧导航为“数据治理”。页面内包含：

- 元数据目录：资产列表、类型/状态/标签/关键字筛选、选择资产。
- 数据血缘：上游、下游、图谱结构和单资产重建。
- 影响分析：资产类型、资产 ID、变更类型和风险结果。
- 资产标签：标签 CRUD 和资产绑定标签。
- 使用统计：低频资产、高频慢查询资产和统计明细。

图谱第一版使用节点表和边表展示，不引入复杂图形库。

## 权限与安全边界

- 普通用户只可查看自己可见数据集、图表、仪表盘关联的元数据。
- 管理员和治理管理权限用户可查看和管理全部元数据。
- 影响分析结果过滤无权限资产。
- 标签管理和 API 同步入口需要管理权限。
- 敏感标签只允许管理员维护。
- 数据源资产不输出密码、用户名、host、token、secret 或完整连接字符串。
- `asset_type` 和 `relation_type` 均使用白名单。
- 搜索使用 Eloquent 条件和 like 查询，不接受原始 SQL。
- 图谱深度最大 5，遍历防止循环。
- API 分页最大 100。

## 当前实现边界

- 不接 Neo4j 或图数据库。
- 不做完整企业级数据治理平台。
- 不做完整 SQL AST 字段级解析。
- 不做跨系统 ETL、调度、Kafka、Flink 作业血缘。
- 不做复杂审批流。
- 不做数据质量规则引擎。
- 不做敏感数据自动识别完整模型。
- 删除前风险检查第一版通过 API 暴露，未强制接入所有删除接口。

## 后续扩展

- 接入 SQL Parser 解析自定义 SQL 数据集的字段级血缘。
- 接入调度系统、ETL 系统和数据仓库元数据，形成跨系统血缘。
- 大型血缘图谱接 Neo4j 或专用图数据库。
- 元数据搜索接 Elasticsearch 或 Meilisearch。
- 资产负责人接组织权限和审批流。
- 敏感字段结合数据脱敏、审计和数据权限策略。
- 高风险变更接审批、通知和强制二次确认。
- 核心指标变更接版本发布、订阅通知和影响确认。
