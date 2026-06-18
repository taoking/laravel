# Laravel BI Platform

Laravel BI Platform 是一个基于 Laravel 13 的轻量 BI 后端项目，覆盖数据源接入、数据集建模、动态查询、图表配置、仪表盘、导入导出、缓存、数据权限、审计日志和监控指标。

当前仓库重点是后端 API 和工程结构，没有内置完整前端应用。后续前端可按 `docs/FEATURE_PAGES.md` 实现 Vue 3 管理端。

## 技术栈

- Laravel 13
- PHP 8.3+
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

## 文档入口

- [Plan 执行日志](docs/PLAN_EXECUTION_LOG.md)：记录 Phase 1 到 Phase 12 的实现过程、产物和验收结果。
- [项目使用说明](docs/PROJECT_USAGE.md)：包含 Docker 启动、本地运行、API 认证、主要接口示例、测试和注意事项。
- [功能页面文档介绍](docs/FEATURE_PAGES.md)：按前端页面/工作台说明功能、接口和交互建议。
- [原始开发计划](plan.md)：完整项目规划。

## 快速启动

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate
```

访问地址：

```text
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
