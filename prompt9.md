# Phase 14：治理闭环 / 删除前风险拦截 / 元数据自动同步

继续执行当前 Laravel 13 + Vue 3 + Docker BI 分析工具项目的下一阶段任务。

## 一、当前项目能力概览

当前系统已经具备：

```text
用户/角色/权限
数据源管理
数据集建模
查询引擎
图表管理
仪表盘管理
导入导出
查询缓存
数据权限
查询日志
ClickHouse 查询加速
ClickHouse 预聚合
StarRocks / Doris OLAP 数据源
BI 语义层 / 指标库
元数据目录 / 数据血缘 / 影响分析
Vue 管理端
```

系统主链路已经从“能查询、能建模、能出图”扩展到了“有指标口径、有血缘、有影响分析”。

## 二、当前仍需补充完善或优化的方向

按生产可用性和业务价值排序，当前主要缺口包括：

1. 删除前风险分析还没有接入实际删除流程。
2. 元数据目录和血缘主要依赖手动命令同步，业务对象变更后不会自动更新治理视图。
3. 高风险删除缺少 `force=true` 二次确认机制。
4. 维度删除接口存在现有 bug：`DimensionController::destroy()` 使用 `$request`，但方法参数未注入 `Request`。
5. 影响分析已能计算风险，但错误响应里缺少统一的阻断原因和处理方式。
6. 数据集字段变更、指标变更、图表变更、仪表盘组件变更后，缓存和指标使用已局部处理，但元数据/血缘未形成闭环。
7. SQL 数据集字段级血缘仍然是边界能力，后续可接 SQL Parser。
8. 前端页面已有基础能力，但复杂编辑器、分页交互和图谱可视化仍可继续增强。
9. 多租户字段存在，但全链路 tenant isolation 尚未完全收敛。
10. 生产监控可继续增加告警、队列失败恢复和任务运行态 dashboard。

本阶段优先处理 1-6，因为它们直接关系到数据治理闭环和危险变更安全。

## 三、本阶段目标

实现一套最小可用的治理闭环：

1. 删除 data source 前检查下游影响。
2. 删除 dataset 前检查下游影响。
3. 删除 metric 前检查下游影响。
4. 删除 dimension 前检查下游影响。
5. 删除 chart 前检查下游影响。
6. 删除 dashboard 前检查下游影响。
7. risk level 为 `high` 或 `critical` 时阻断删除。
8. 请求显式传入 `force=true` 时允许继续删除。
9. 删除阻断响应中返回可读错误，提示先查看影响分析或使用 force 确认。
10. 业务对象 create/update/delete 后自动同步相关 metadata asset。
11. 业务对象 create/update 后自动重建相关 lineage。
12. dataset field 更新、dataset sync fields 后自动同步 dataset 和字段血缘。
13. dashboard widget 增删改后自动同步 dashboard 血缘。
14. 修复 `DimensionController::destroy()` 缺少 `Request` 参数的问题。
15. 补测试覆盖删除阻断、force 删除、自动同步和维度删除接口。

## 四、本阶段明确不做

本阶段不做：

1. 不接 Neo4j 或图数据库。
2. 不做复杂审批流。
3. 不做 SQL AST 字段级解析。
4. 不做跨系统 ETL / 调度血缘。
5. 不做复杂图谱可视化。
6. 不重写已有查询引擎。
7. 不改变已有查询 API 协议。
8. 不改变现有缓存、加速和权限主链路。

## 五、后端设计

### 1. 新增 MetadataChangeGuardService

职责：

1. 删除前确保目标资产尽量同步到 `metadata_assets`。
2. 调用 `MetadataImpactAnalysisService` 计算下游影响。
3. 当风险为 `high` 或 `critical` 且 `force=false` 时抛出 validation error。
4. 当 `force=true` 时放行。
5. 当元数据尚未同步且无法分析时，允许删除但不误报。

建议方法：

```php
guardDelete(string $assetType, int $assetId, ?User $actor, bool $force = false): ?array
```

### 2. 新增 MetadataLifecycleService

职责：

1. 为业务对象变更提供轻量元数据同步入口。
2. 同步单个资产或一个小范围资产。
3. 删除成功后将对应 metadata asset 标记为 `archived`。
4. 避免业务服务直接拼装元数据细节。

建议方法：

```php
sync(string $assetType, int $assetId): void
archive(string $assetType, int $assetId): void
```

### 3. 扩展 MetadataSyncService

新增单资产同步方法：

```php
syncAssetMetadata(string $assetType, int $assetId, bool $withLineage = true): array
```

支持：

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
```

### 4. 接入删除保护

接入对象：

```text
DataSourceService::delete
DatasetService::delete
MetricService::delete
DimensionService::delete
ChartService::delete
DashboardService::delete
```

API 请求方式：

```text
DELETE /api/charts/{chart}
DELETE /api/charts/{chart}?force=1
```

也支持 JSON body：

```json
{
  "force": true
}
```

### 5. 接入自动同步

create/update 后同步：

```text
data source
dataset
metric
dimension
chart
dashboard
```

特殊场景：

```text
dataset sync fields 后同步 dataset 和 dataset_field lineage
dataset field update 后同步 dataset
dashboard widget add/update/delete 后同步 dashboard
```

delete 成功后：

```text
metadata asset status = archived
```

## 六、安全要求

1. 删除保护不得绕过原有权限校验。
2. 普通用户只能看到自己有权限的影响分析结果。
3. `force=true` 只表示用户确认风险，不表示绕过权限。
4. 元数据同步不得输出数据源密码、用户名、host、token、secret、connection string。
5. asset type 仍必须走白名单。
6. 图谱和影响分析仍必须限制深度并避免递归循环。
7. 删除阻断不能破坏现有查询链路。

## 七、测试要求

新增或扩展 Feature 测试覆盖：

1. chart 被 dashboard 使用时，普通删除被阻断。
2. chart 删除传 `force=true` 时允许删除，并将 metadata asset 标记为 `archived`。
3. dataset 字段或 metric 创建后自动出现在 metadata assets 中。
4. chart 创建后自动建立 chart -> metric / dataset 血缘。
5. dashboard widget 添加后自动建立 dashboard -> chart 血缘。
6. dimension 删除接口不再因为缺少 `$request` 报错。
7. 高风险删除阻断不会绕过原有权限。

## 八、文档要求

更新：

```text
docs/bi-metadata-lineage.md
docs/PLAN_EXECUTION_LOG.md
```

说明：

1. 删除前风险检查已接入业务删除流程。
2. `force=true` 的语义和边界。
3. 自动同步覆盖范围。
4. 当前仍未做复杂审批流和 SQL AST 血缘。

## 九、验收标准

完成后需要满足：

1. 高风险 chart 删除会被阻断。
2. `force=true` 可以继续删除高风险对象。
3. 删除后 metadata asset 变为 `archived`。
4. create/update 后元数据资产自动同步。
5. chart/dashboard/dataset/metric/dimension 血缘能自动刷新。
6. 维度删除接口正常工作。
7. 不破坏现有查询、图表、仪表盘和语义层流程。
8. `php artisan test` 尽量通过。
9. `vendor/bin/pint --test` 尽量通过。
10. `npm run build` 尽量通过。

## 十、运行命令

尽量运行：

```text
php artisan test
vendor/bin/pint --test
npm run build
php artisan route:list --path=api
```

## 十一、最终输出要求

任务完成后，在文档中写明：

1. 本次完成内容。
2. 修改文件列表。
3. 新增服务列表。
4. 变更 API 行为。
5. 删除风险拦截规则。
6. force 参数说明。
7. 自动同步范围。
8. 测试文件和测试结果。
9. 运行过的命令。
10. 当前边界。
11. 后续建议。
