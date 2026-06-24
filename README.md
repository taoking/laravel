# Laravel BI Platform

Laravel BI Platform 是一个基于 Laravel 13 和 Vue 3 的轻量 BI 管理平台，覆盖数据源接入、数据集建模、语义层指标库、动态查询、图表配置、仪表盘、导入导出、缓存、数据权限、审计日志、监控指标和管理端页面。

当前仓库包含 Laravel 后端 API 和内置于 Laravel Vite 的 Vue 3 管理端。

## 技术栈

- Laravel 13
- PHP 8.3+
- Vue 3
- Vue Router
- Pinia
- Axios
- ECharts
- Vite
- Laravel Sanctum
- Laravel Queue
- MySQL
- Redis
- MinIO
- Docker Compose
- PHPUnit
- Laravel Pint

## 核心能力

- 用户、角色、权限和组织部门。
- MySQL、StarRocks、Doris 数据源管理、连接测试、元数据同步。
- 数据集建模、字段分类、维度/指标配置。
- BI 语义层：指标分类、指标库、维度管理、公式校验、版本、血缘依赖、使用记录和影响分析。
- 动态 SQL 查询引擎、查询缓存、查询日志。
- 图表配置、图表数据预览和查询。
- 仪表盘编排、全局筛选、组件布局、公开分享。
- CSV/XLSX 文件导入，自动生成物理表和数据集。
- 图表 CSV/XLSX 导出，仪表盘 PDF 导出。
- 资源权限、行级数据权限、列级字段权限。
- 操作日志、登录日志、查询日志、导出日志。
- 健康检查和 Prometheus metrics。
- BI 查询加速：ClickHouse 明细表、预聚合表、加速 profile、同步任务、自动路由、fallback 和命中日志。
- OLAP 直连数据源：StarRocks / Doris 一等数据源、SQL 方言、Explain、物化视图元数据、查询日志引擎字段。
- Vue 管理端：登录、主布局、菜单路由、数据源、数据集、图表、仪表盘、导入导出、查询日志、权限管理、系统监控。

## 文档入口

- [Plan 执行日志](docs/PLAN_EXECUTION_LOG.md)：记录 Phase 1 到 Phase 13 的实现过程、产物和验收结果。
- [项目使用说明](docs/PROJECT_USAGE.md)：包含 Docker 启动、本地运行、前端管理端、API 认证、主要接口示例、测试和注意事项。
- [功能页面文档介绍](docs/FEATURE_PAGES.md)：按前端页面/工作台说明已实现功能、接口和交互。
- [BI 查询加速方案](docs/bi-acceleration.md)：说明 ClickHouse 加速层、profile、同步任务、查询路由、fallback、缓存 key 和边界。
- [ClickHouse 查询加速最小闭环](docs/bi-acceleration-clickhouse.md)：Phase 10.1 的 ClickHouse 明细表加速闭环、API 和验收边界。
- [预聚合表 / 物化视图加速](docs/bi-acceleration-aggregate.md)：Phase 10.2 的聚合定义、构建、命中、回退、缓存和日志边界。
- [查询日志推荐、自动刷新和收益统计](docs/bi-acceleration-recommendation.md)：Phase 10.3 的推荐规则、刷新计划、收益报表、命令和生产边界。
- [StarRocks / Doris 一等 OLAP 数据源](docs/bi-olap-starrocks-doris.md)：Phase 10.4 的直连 OLAP 数据源、SQL 方言、Explain、查询日志和物化视图边界。
- [BI 语义层 / 指标库 / 口径治理](docs/bi-semantic-layer.md)：Phase 11 的指标、维度、公式、版本、血缘、影响分析和查询接入边界。
- [原始开发计划](plan.md)：完整项目规划。

## 快速启动

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate
npm install
npm run build
```

访问地址：

```text
管理端: http://localhost:8080
API: http://localhost:8080/api
MinIO Console: http://localhost:9001
ClickHouse HTTP: http://localhost:8123
```

MinIO 默认账号：

```text
username: minioadmin
password: minioadmin
bucket: bi-platform
```

如果 bucket 不存在，需要在 MinIO Console 中创建 `bi-platform`。

## 常用命令

```bash
php artisan test
vendor/bin/pint
npm run build
php artisan route:list --path=api
php artisan migrate --pretend --database=sqlite
php artisan bi:acceleration:recommend --dry-run
php artisan bi:acceleration:refresh-due --dry-run
php artisan bi:acceleration:benefit-report
```

当前验证基线：

```text
74 tests, 641 assertions
168 API routes
```

## API 认证

登录获取 token：

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secret-password",
    "device_name": "curl"
  }'
```

后续请求：

```text
Authorization: Bearer <token>
```

## 主要接口分组

- Auth：`/api/auth/*`
- Users：`/api/users`
- Roles：`/api/roles`
- Permissions：`/api/permissions`
- Data Sources：`/api/data-sources`
- Datasets：`/api/datasets`
- Query：`/api/query/execute`
- Explain：`/api/datasets/{dataset}/explain`、`/api/charts/{chart}/explain`
- Semantic Layer：`/api/metric-categories`、`/api/semantic-metrics`、`/api/dimensions`、`/api/datasets/{dataset}/semantic-layer`
- Charts：`/api/charts`
- Dashboards：`/api/dashboards`
- Imports：`/api/import-tasks`
- Exports：`/api/export-tasks`
- Data Permissions：`/api/resource-permissions`、`/api/data-permission-rules`、`/api/column-permission-rules`
- Acceleration：`/api/acceleration/profiles`、`/api/acceleration/aggregates`、`/api/acceleration/recommendations`、`/api/acceleration/refresh-schedules`、`/api/acceleration/benefit-report`、`/api/acceleration/tasks`、`/api/datasets/{dataset}/acceleration`
- OLAP Materialized Views：`/api/data-sources/{data_source}/materialized-views`
- Audit：`/api/operation-logs`、`/api/login-logs`、`/api/query-logs`、`/api/export-logs`
- Monitor：`/api/health`、`/api/metrics`

完整使用方式见 [项目使用说明](docs/PROJECT_USAGE.md)。

## 说明

- 生产环境应使用 Redis queue，并运行 queue worker。
- 生产环境如启用加速自动刷新，需要运行 Laravel scheduler，例如每分钟执行 `php artisan schedule:run`。
- 导入和导出依赖 MinIO/S3 disk。
- 查询加速依赖 ClickHouse 和队列 worker；预聚合失败会先回退 ClickHouse 明细表，明细不可用时再回退原始数据源查询。
- StarRocks / Doris 作为直连 OLAP 数据源使用，不依赖主 docker-compose；推荐连接外部 FE 查询入口，数据同步由独立 ETL / CDC 链路负责。
- 语义层公式只支持指标编码、数字、四则运算和括号；不支持 SQL 片段、函数调用或跨数据集指标。
- `/api/health*` 和 `/api/metrics` 当前为公开接口，生产环境建议在网关或 middleware 中限制访问。
- 图表查询默认启用缓存；配置变更、数据集字段变更会清理相关缓存。
