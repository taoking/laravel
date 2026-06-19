# Laravel BI Platform

Laravel BI Platform 是一个基于 Laravel 13 和 Vue 3 的轻量 BI 管理平台，覆盖数据源接入、数据集建模、动态查询、图表配置、仪表盘、导入导出、缓存、数据权限、审计日志、监控指标和管理端页面。

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
- MySQL 数据源管理、连接测试、元数据同步。
- 数据集建模、字段分类、维度/指标配置。
- 动态 SQL 查询引擎、查询缓存、查询日志。
- 图表配置、图表数据预览和查询。
- 仪表盘编排、全局筛选、组件布局、公开分享。
- CSV/XLSX 文件导入，自动生成物理表和数据集。
- 图表 CSV/XLSX 导出，仪表盘 PDF 导出。
- 资源权限、行级数据权限、列级字段权限。
- 操作日志、登录日志、查询日志、导出日志。
- 健康检查和 Prometheus metrics。
- Vue 管理端：登录、主布局、菜单路由、数据源、数据集、图表、仪表盘、导入导出、查询日志、权限管理、系统监控。

## 文档入口

- [Plan 执行日志](docs/PLAN_EXECUTION_LOG.md)：记录 Phase 1 到 Phase 13 的实现过程、产物和验收结果。
- [项目使用说明](docs/PROJECT_USAGE.md)：包含 Docker 启动、本地运行、前端管理端、API 认证、主要接口示例、测试和注意事项。
- [功能页面文档介绍](docs/FEATURE_PAGES.md)：按前端页面/工作台说明已实现功能、接口和交互。
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
```

当前验证基线：

```text
48 tests, 394 assertions
90 API routes
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
- Charts：`/api/charts`
- Dashboards：`/api/dashboards`
- Imports：`/api/import-tasks`
- Exports：`/api/export-tasks`
- Data Permissions：`/api/resource-permissions`、`/api/data-permission-rules`、`/api/column-permission-rules`
- Audit：`/api/operation-logs`、`/api/login-logs`、`/api/query-logs`、`/api/export-logs`
- Monitor：`/api/health`、`/api/metrics`

完整使用方式见 [项目使用说明](docs/PROJECT_USAGE.md)。

## 说明

- 生产环境应使用 Redis queue，并运行 queue worker。
- 导入和导出依赖 MinIO/S3 disk。
- `/api/health*` 和 `/api/metrics` 当前为公开接口，生产环境建议在网关或 middleware 中限制访问。
- 图表查询默认启用缓存；配置变更、数据集字段变更会清理相关缓存。
