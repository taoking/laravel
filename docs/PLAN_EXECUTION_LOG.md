# Plan Execution Log

记录 `plan.md` 从 Phase 1 到 Phase 13 的执行过程、核心产物与验收结果。

执行日期：2026-06-18  
项目目录：`/Users/tao/workspace/code/laravel/laravel`  
最终验证：`php artisan test` 通过，48 tests，394 assertions；`npm run build` 通过；`vendor/bin/pint` 通过；`php artisan route:list --path=api` 输出 90 条 API 路由；`php artisan migrate --pretend --database=sqlite` 通过。

## 总体执行原则

- 按 `plan.md` 阶段顺序推进，每个阶段完成后运行局部或完整测试。
- 模块按 `app/Modules/*` 组织，Controller 只处理请求/响应，业务逻辑进入 Service。
- 数据库结构以 migration 管理，API 输出统一走 `App\Support\Response\ApiResponse`。
- 查询相关能力集中在 Query Engine，图表、仪表盘、导入导出复用查询服务。
- 异步任务使用 Laravel Queue，测试环境使用 sync queue 直接断言任务结果。

## Phase 1 项目初始化与基础工程

目标：搭建 Docker 开发环境、认证、用户、角色和权限基础。

主要产物：

- Docker 服务：`nginx`、`php-fpm`、`mysql`、`redis`、`minio`、`queue-worker`、`scheduler`、`node`。
- 认证：Sanctum 登录、登出、当前用户接口。
- 用户组织：`organizations`、`departments`、用户组织/部门/状态字段。
- RBAC：`roles`、`permissions`、`role_user`、`permission_role`。
- 统一 API 响应与异常包装。

关键接口：

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `apiResource users`
- `apiResource roles`
- `apiResource permissions`

验收：

- 用户可登录并获取 token。
- 用户、角色、权限可增删改查。
- 未认证请求返回统一 `40100`。

## Phase 2 数据源管理模块

目标：支持 MySQL 数据源配置、连通性测试、元数据读取和同步。

主要产物：

- 表：`data_sources`、`data_source_tables`、`data_source_fields`。
- 模型：`DataSource`、`DataSourceTable`、`DataSourceField`。
- 服务：`DataSourceService`、`DataSourceMetadataService`、`DataSourceConnectionFactory`、`DataSourcePasswordEncryptor`。
- 驱动：`MySqlMetadataDriver`。

关键接口：

- `apiResource data-sources`
- `POST /api/data-sources/{data_source}/test`
- `POST /api/data-sources/{data_source}/sync`
- `GET /api/data-sources/{data_source}/tables`
- `GET /api/data-sources/{data_source}/tables/{table}/fields`

验收：

- 密码加密存储。
- 数据源连接可测试。
- 可读取表和字段元数据。
- 元数据缓存和刷新逻辑可用。

## Phase 3 数据集建模模块

目标：基于数据源表构建数据集，维护维度、指标、字段语义和预览能力。

主要产物：

- 表：`datasets`、`dataset_tables`、`dataset_fields`、`dataset_filters`。
- 服务：`DatasetService`、`DatasetFieldClassifier`、`DatasetPreviewService`。
- 资源：`DatasetResource`、`DatasetTableResource`、`DatasetFieldResource`。

关键接口：

- `apiResource datasets`
- `POST /api/datasets/{dataset}/sync-fields`
- `GET /api/datasets/{dataset}/fields`
- `PUT /api/datasets/{dataset}/fields/{field}`
- `POST /api/datasets/{dataset}/preview`

验收：

- 创建数据集时自动同步字段。
- 字段可配置展示名、维度/指标、聚合方式和可见性。
- 支持数据集预览。

## Phase 4 查询引擎模块

目标：实现动态 SQL 查询能力，包括维度、指标、过滤、排序、缓存和日志。

主要产物：

- DTO：`QueryRequestDTO`、`DimensionDTO`、`MetricDTO`、`FilterDTO`、`SortDTO`。
- 编译器：`SqlCompiler`、`DimensionCompiler`、`MetricCompiler`、`FilterCompiler`、`SortCompiler`。
- 服务：`QueryService`、`QueryExecutor`、`QueryCacheService`、`QueryLogService`。
- 表：`query_logs`。

关键接口：

- `POST /api/query/execute`

验收：

- 支持 group by 查询、聚合查询、过滤和排序。
- 字段白名单校验和 SQL 标识符保护可用。
- 查询结果可缓存，查询日志记录成功/失败、耗时、行数和缓存命中。

## Phase 5 图表模块

目标：保存图表配置并基于 Query Engine 返回图表数据。

主要产物：

- 表：`charts`。
- 服务：`ChartService`、`ChartDataService`、`ChartConfigValidator`、`ChartQueryBuilder`。
- 支持图表类型：`metric_card`、`bar`、`line`、`pie`、`table`。

关键接口：

- `apiResource charts`
- `POST /api/charts/preview`
- `POST /api/charts/{chart}/data`

验收：

- 图表配置字段必须属于数据集。
- 图表数据接口复用 Query Engine。
- 预览接口不持久化图表。

## Phase 6 仪表盘模块

目标：支持仪表盘、组件布局、全局筛选、图表联动和公开分享。

主要产物：

- 表：`dashboards`、`dashboard_widgets`、`dashboard_filters`、`dashboard_linkages`、`dashboard_shares`。
- 服务：`DashboardService`、`DashboardDataService`、`DashboardFilterBuilder`。

关键接口：

- `apiResource dashboards`
- `POST /api/dashboards/{dashboard}/widgets`
- `PUT /api/dashboards/{dashboard}/widgets/{widget}`
- `DELETE /api/dashboards/{dashboard}/widgets/{widget}`
- `POST /api/dashboards/{dashboard}/data`
- `POST /api/dashboards/{dashboard}/share`
- `GET /api/share/dashboards/{token}`

验收：

- 可管理仪表盘组件布局。
- 仪表盘数据刷新会调用所有组件图表。
- 全局筛选会合并到每个图表查询。
- 分享链接可公开读取。

## Phase 7 文件导入模块

目标：支持 CSV/XLSX 上传、异步导入、物理表生成和数据集生成。

主要产物：

- 表：`import_tasks`、`import_task_logs`、`uploaded_tables`。
- 服务：`FileUploadService`、`CsvParser`、`ExcelParser`、`ImportSchemaInferService`、`ImportDataWriter`、`CreateDatasetFromImportService`、`ImportTaskProcessor`、`ImportTaskService`。
- Job：`ProcessImportTaskJob`。
- MinIO/S3 依赖：`league/flysystem-aws-s3-v3`。

关键接口：

- `POST /api/import-tasks`
- `GET /api/import-tasks`
- `GET /api/import-tasks/{import_task}`
- `POST /api/import-tasks/{import_task}/retry`
- `DELETE /api/import-tasks/{import_task}`

验收：

- CSV 上传后保存到 MinIO disk。
- sync queue 测试中任务直接处理完成。
- 自动创建物理导入表、数据源元数据、数据集和字段。
- 支持失败任务重试和删除任务时清理物理表/文件。

## Phase 8 导出模块

目标：支持图表数据导出、仪表盘 PDF 导出、异步任务和下载。

主要产物：

- 表：`export_tasks`。
- 服务：`ExportTaskService`、`ExportTaskProcessor`、`ChartExportService`、`DashboardExportService`、`CsvExportService`、`ExcelExportService`、`PdfExportService`。
- Job：`ProcessExportTaskJob`。

关键接口：

- `POST /api/export-tasks`
- `GET /api/export-tasks`
- `GET /api/export-tasks/{export_task}`
- `GET /api/export-tasks/{export_task}/download`
- `POST /api/export-tasks/{export_task}/retry`

验收：

- 图表数据可导出 CSV/XLSX。
- 仪表盘可导出简单 PDF。
- 导出文件保存到 MinIO disk。
- 下载接口只允许任务创建者访问。

## Phase 9 缓存模块

目标：统一缓存 key，支持图表查询缓存与缓存失效。

主要产物：

- 服务：`CacheKeyBuilder`、`ChartCacheService`、`DatasetCacheService`、`DashboardCacheService`、`PermissionCacheService`。
- Query cache key 使用 `bi:*` 命名。
- 图表查询默认启用缓存。
- 图表、数据集、仪表盘、权限变更时清理对应缓存。

关键行为：

- `bi:chart:{id}:query:*`
- `bi:chart:{id}:query_keys`
- `bi:dataset:{id}:schema`
- `bi:user:{id}:permissions`

验收：

- 同一图表重复查询第二次命中缓存。
- 图表配置更新后查询缓存清空。
- 数据集字段更新后关联图表查询缓存清空。
- 查询日志能看到 `cached=true/false`。

## Phase 10 数据权限模块

目标：实现资源权限、行级数据权限和列级字段权限。

主要产物：

- 表：`resource_permissions`、`data_permission_rules`、`column_permission_rules`。
- 模型：`ResourcePermission`、`DataPermissionRule`、`ColumnPermissionRule`。
- 服务：`DataPermissionService`、`DataPermissionSubjectResolver`。
- Query Engine 集成：查询前校验 dataset 资源权限；SQL 编译时追加行级权限条件；字段校验时拒绝隐藏字段。

关键接口：

- `apiResource resource-permissions`
- `apiResource data-permission-rules`
- `apiResource column-permission-rules`

验收：

- 可给角色配置 dataset 访问权限。
- 没有权限的用户不能查询对应 dataset。
- 行级规则如 `province = GD` 会自动追加到 SQL。
- 列级 `hidden/masked` 会阻止对应字段查询。

## Phase 11 审计日志与查询日志模块

目标：记录登录、操作、查询和导出日志，并提供后台查询接口。

主要产物：

- 表：`operation_logs`、`login_logs`、`export_logs`。
- 增强表：`query_logs` 增加 `tenant_id`、`chart_id`、`dashboard_id`、`is_slow`。
- Middleware：`RecordOperationLog`。
- 服务：`OperationLogService`、`LoginLogService`、`ExportLogService`。

关键接口：

- `GET /api/operation-logs`
- `GET /api/login-logs`
- `GET /api/export-logs`
- `GET /api/query-logs`

验收：

- 登录成功/失败写入 login logs。
- API 请求写入 operation logs。
- 图表查询写入带 chart context 的 query logs。
- 导出任务完成写入 export logs。

## Phase 12 监控与生产级增强

目标：增加健康检查和 Prometheus 指标接口。

主要产物：

- 服务：`HealthCheckService`、`MetricsService`。
- 控制器：`HealthController`、`MetricsController`。

关键接口：

- `GET /api/health`
- `GET /api/health/database`
- `GET /api/health/redis`
- `GET /api/health/storage`
- `GET /api/health/queue`
- `GET /api/metrics`

验收：

- 健康检查接口可公开访问。
- 数据库、Redis、存储、队列检查不会因为单项失败导致接口 500。
- Prometheus 文本指标包含查询、导入、导出和队列积压指标。

## Phase 13 Vue 前端管理端

目标：在 Laravel Vite 资源体系内落地 Vue 3 管理端，覆盖登录、主布局、菜单路由、业务管理页面、Axios API 封装、Pinia 状态管理和 ECharts 图表渲染。

主要产物：

- 前端依赖：`vue`、`vue-router`、`pinia`、`axios`、`echarts`、`@vitejs/plugin-vue`、`@lucide/vue`。
- Vite 配置：`vite.config.js` 接入 Vue 插件，Laravel 继续使用 `resources/css/app.css` 和 `resources/js/app.js` 作为入口。
- SPA 入口：`resources/views/welcome.blade.php` 改为 Vue 挂载容器；`routes/web.php` 使用非 `/api` catch-all 返回前端页面。
- 路由与状态：`resources/js/router/index.js`、`resources/js/stores/auth.js`。
- API 层：`resources/js/services/http.js`、`resources/js/services/api.js`，统一注入 Bearer token、处理 401 和分页数据。
- 主布局与菜单：`resources/js/layouts/AppShell.vue`、`resources/js/navigation.js`。
- 通用组件：`DataTable.vue`、`StatusBadge.vue`、`PageHeader.vue`、`JsonTextarea.vue`、`ChartRenderer.vue`。
- 页面：
  - `LoginView.vue`
  - `DashboardOverviewView.vue`
  - `DataSourcesView.vue`
  - `DatasetsView.vue`
  - `ChartsView.vue`
  - `DashboardsView.vue`
  - `ImportTasksView.vue`
  - `ExportTasksView.vue`
  - `QueryLogsView.vue`
  - `PermissionsView.vue`
  - `MonitorView.vue`

关键行为：

- 未登录访问管理端路由时跳转 `/login`。
- 登录成功后保存 Sanctum token 到 `localStorage`，Axios 自动注入 `Authorization: Bearer <token>`。
- 数据源页面支持创建、编辑、删除、测试连接、同步元数据、查看表字段。
- 数据集页面支持创建、编辑、删除、同步字段、字段语义配置和数据预览。
- 图表页面支持图表配置、JSON 查询配置、预览接口和 ECharts 渲染。
- 仪表盘页面支持创建、编辑、组件挂载、数据刷新和分享 token 创建。
- 导入页面支持文件上传、任务列表、详情日志、重试和删除。
- 导出页面支持图表 CSV/XLSX、仪表盘 PDF 任务创建、重试和带 Bearer token 下载。
- 查询日志页面支持按 dataset/chart/dashboard/status 筛选。
- 权限页面支持用户、角色、权限、资源权限、行级规则、列级规则基础维护。
- 监控页面展示健康检查和 Prometheus 文本指标。

验收：

- `npm run build` 通过。
- `php artisan test` 通过，48 tests，394 assertions。
- `php artisan route:list --path=api` 仍输出 90 条 API 路由。
- 本地启动 `php artisan serve --host=127.0.0.1 --port=8000` 与 `npm run dev -- --host 127.0.0.1 --port 5173` 后，`GET /login` 返回 Vue SPA 容器。
- SPA catch-all 已排除 Session/CSRF middleware，登录页面不依赖数据库 session；认证仍由 API token 负责。

## 最终验收记录

最终执行的验证命令：

```bash
vendor/bin/pint
php artisan test
npm run build
php artisan route:list --path=api
php artisan migrate --pretend --database=sqlite
```

结果：

- Pint：通过。
- Test：48 tests，394 assertions，全部通过。
- Frontend build：通过，存在 ECharts 等依赖导致的单 chunk 体积提示。
- API routes：90 条。
- Migration pretend：通过。
- `laravel_live` 临时 SQLite 文件已清理。

## 已知边界

- 当前 Vue 管理端是 Laravel 内置 SPA，不是独立前端仓库。
- 管理端页面已覆盖核心功能流程；复杂查询构建和仪表盘拖拽布局保留为 JSON 配置入口，后续可升级为可视化编辑器。
- 导入生成的物理表写入当前应用数据库；导入元数据会生成 MySQL 类型的数据源记录。
- 仪表盘 PDF 为可用的轻量版 PDF 文本导出，不是像素级页面截图。
- Grafana/Loki 在计划中属于部署层增强，当前代码提供 Prometheus metrics 和健康检查接口，方便后续接入。
