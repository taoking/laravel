# 项目使用说明

本文档说明如何启动、配置、验证和使用 Laravel BI Platform 管理端与后端 API。

完整技术架构、流程图、接口清单和表结构见 [项目详细文档](PROJECT_DOCUMENTATION.md)。

## 运行环境

推荐环境：

- PHP 8.3+
- Composer 2
- Node.js 20+
- npm 10+
- Docker / Docker Compose
- MySQL 8.4
- Redis 7.4
- MinIO

项目已提供 Docker 编排，包含：

- `nginx`：Web 入口。
- `php-fpm`：Laravel Web 运行环境。
- `mysql`：业务元数据与导入物理表。
- `redis`：缓存、队列、会话。
- `minio`：上传文件、导出文件存储。
- `queue-worker`：异步任务消费。
- `scheduler`：Laravel 定时任务。
- `node`：前端开发预留。

## 快速启动

1. 准备环境文件。

```bash
cp .env.example .env
```

2. 启动容器。

```bash
docker compose up -d --build
```

3. 安装 PHP 依赖。

```bash
docker compose exec php-fpm composer install
```

4. 生成应用密钥。

```bash
docker compose exec php-fpm php artisan key:generate
```

5. 执行迁移。

```bash
docker compose exec php-fpm php artisan migrate
```

6. 可选：执行 seed。

```bash
docker compose exec php-fpm php artisan db:seed
```

7. 访问服务。

```text
管理端: http://localhost:8080
API: http://localhost:8080/api
MinIO Console: http://localhost:9001
```

默认 MinIO 账号来自 `.env.example`：

```text
username: minioadmin
password: minioadmin
bucket: bi-platform
```

如果 MinIO 中没有 `bi-platform` bucket，需要在 MinIO Console 中手动创建。

## 前端管理端

前端管理端使用 Laravel Vite 内置 SPA：

- Blade 入口：`resources/views/welcome.blade.php`
- Vue 入口：`resources/js/app.js`
- 路由：`resources/js/router/index.js`
- 状态管理：`resources/js/stores/auth.js`
- API 封装：`resources/js/services/http.js`、`resources/js/services/api.js`
- 页面目录：`resources/js/pages`

首次安装前端依赖：

```bash
npm install
```

本地开发需要同时启动 Laravel 和 Vite：

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev -- --host 127.0.0.1 --port 5173
```

访问：

```text
管理端: http://127.0.0.1:8000
登录页: http://127.0.0.1:8000/login
```

生产构建：

```bash
npm run build
```

说明：

- `/api/*` 继续由 API 路由处理。
- 其他前端路径由 `routes/web.php` catch-all 返回 Vue SPA。
- SPA 路由排除了 Session/CSRF middleware；管理端认证使用 Sanctum Bearer token，不依赖服务端 session。
- `public/hot` 存在时 Laravel 会读取 Vite 开发服务器资源；停止 Vite 后如需使用构建产物，应确保 `public/hot` 不存在。

## 本地非 Docker 运行

本地运行适合快速测试 SQLite 或本机 MySQL。

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
npm install
npm run dev -- --host 127.0.0.1 --port 5173
```

如果使用队列异步处理导入导出，需要启动 worker：

```bash
php artisan queue:work
```

测试环境默认使用 SQLite 内存库和 sync queue，不需要额外服务。

## 常用命令

运行测试：

```bash
php artisan test
```

代码格式化：

```bash
vendor/bin/pint
```

查看 API 路由：

```bash
php artisan route:list --path=api
```

迁移预演：

```bash
php artisan migrate --pretend --database=sqlite
```

清理配置缓存：

```bash
php artisan config:clear
```

前端开发服务器：

```bash
npm run dev -- --host 127.0.0.1 --port 5173
```

前端生产构建：

```bash
npm run build
```

## 关键环境变量

数据库：

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bi_platform
DB_USERNAME=bi_user
DB_PASSWORD=bi_password
```

Redis：

```env
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

MinIO：

```env
FILESYSTEM_DISK=local
IMPORT_FILESYSTEM_DISK=minio
EXPORT_FILESYSTEM_DISK=minio
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_BUCKET=bi-platform
MINIO_REGION=us-east-1
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

## API 响应格式

成功响应：

```json
{
  "code": 0,
  "message": "success",
  "data": {}
}
```

分页响应：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [],
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 0
    }
  }
}
```

错误响应：

```json
{
  "code": 40001,
  "message": "Invalid request parameters",
  "errors": {}
}
```

常见错误码：

- `40001`：请求参数错误。
- `40100`：未认证。
- `40300`：无权限。
- `40400`：资源不存在。
- `40500`：HTTP 方法不允许。
- `50000`：服务端错误。

## 认证流程

管理端登录页使用同一组接口。登录成功后，前端会保存 `data.token` 到 `localStorage.bi_token`，并在后续 Axios 请求中添加 `Authorization: Bearer <token>`。

登录：

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "secret-password",
    "device_name": "curl"
  }'
```

接口返回 `data.token`。后续请求添加：

```text
Authorization: Bearer <token>
```

当前用户：

```bash
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <token>"
```

退出：

```bash
curl -X POST http://localhost:8080/api/auth/logout \
  -H "Authorization: Bearer <token>"
```

## 主链路使用顺序

典型 BI 使用流程：

```text
登录
-> 创建数据源
-> 测试数据源连接
-> 同步数据源元数据
-> 创建数据集
-> 同步/配置数据集字段
-> 创建图表
-> 查询图表数据
-> 创建仪表盘
-> 添加图表组件
-> 刷新仪表盘数据
-> 配置权限/缓存/导入导出/监控
```

管理端对应菜单：

```text
首页概览
数据源
数据集
图表配置
仪表盘
导入任务
导出任务
查询日志
权限管理
系统监控
```

## 数据源管理

创建 MySQL 数据源：

```bash
curl -X POST http://localhost:8080/api/data-sources \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Analytics MySQL",
    "type": "mysql",
    "host": "mysql",
    "port": 3306,
    "database_name": "analytics",
    "username": "reporter",
    "password": "secret",
    "charset": "utf8mb4",
    "timezone": "+00:00"
  }'
```

测试连接：

```bash
curl -X POST http://localhost:8080/api/data-sources/1/test \
  -H "Authorization: Bearer <token>"
```

同步元数据：

```bash
curl -X POST http://localhost:8080/api/data-sources/1/sync \
  -H "Authorization: Bearer <token>"
```

查看表和字段：

```bash
curl http://localhost:8080/api/data-sources/1/tables \
  -H "Authorization: Bearer <token>"

curl http://localhost:8080/api/data-sources/1/tables/orders/fields \
  -H "Authorization: Bearer <token>"
```

## 数据集与查询

创建数据集：

```bash
curl -X POST http://localhost:8080/api/datasets \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Orders Dataset",
    "data_source_id": 1,
    "dataset_type": "single_table",
    "main_table": "orders"
  }'
```

执行查询：

```bash
curl -X POST http://localhost:8080/api/query/execute \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "dataset_id": 1,
    "dimensions": [
      {"field": "province"}
    ],
    "metrics": [
      {"field": "amount", "aggregate": "sum", "alias": "amount_sum"}
    ],
    "filters": [
      {"field": "year", "operator": "=", "value": 2026}
    ],
    "sorts": [
      {"field": "amount_sum", "direction": "desc"}
    ],
    "limit": 100,
    "use_cache": true
  }'
```

## 图表与仪表盘

创建图表：

```bash
curl -X POST http://localhost:8080/api/charts \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sales by Province",
    "dataset_id": 1,
    "chart_type": "bar",
    "config_json": {
      "dimensions": [{"field": "province"}],
      "metrics": [{"field": "amount", "aggregate": "sum", "alias": "amount_sum"}],
      "sorts": [{"field": "amount_sum", "direction": "desc"}],
      "limit": 10
    },
    "style_json": {
      "title": "Sales by Province"
    }
  }'
```

获取图表数据：

```bash
curl -X POST http://localhost:8080/api/charts/1/data \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "filters": [
      {"field": "province", "operator": "=", "value": "GD"}
    ]
  }'
```

创建仪表盘：

```bash
curl -X POST http://localhost:8080/api/dashboards \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Executive Dashboard",
    "global_filters_json": [
      {"field": "year", "operator": "=", "value": 2026}
    ],
    "filters": [
      {
        "field_name": "province",
        "label": "Province",
        "filter_type": "select",
        "config_json": {"operator": "="}
      }
    ]
  }'
```

添加仪表盘组件：

```bash
curl -X POST http://localhost:8080/api/dashboards/1/widgets \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "chart_id": 1,
    "x": 0,
    "y": 0,
    "w": 6,
    "h": 4
  }'
```

## 文件导入

上传 CSV 并生成数据集：

```bash
curl -X POST http://localhost:8080/api/import-tasks \
  -H "Authorization: Bearer <token>" \
  -F "file=@sales.csv"
```

查看任务：

```bash
curl http://localhost:8080/api/import-tasks/1 \
  -H "Authorization: Bearer <token>"
```

重试任务：

```bash
curl -X POST http://localhost:8080/api/import-tasks/1/retry \
  -H "Authorization: Bearer <token>"
```

## 数据导出

创建图表 CSV 导出任务：

```bash
curl -X POST http://localhost:8080/api/export-tasks \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "export_type": "csv",
    "source_type": "chart",
    "source_id": 1
  }'
```

下载导出文件：

```bash
curl -L http://localhost:8080/api/export-tasks/1/download \
  -H "Authorization: Bearer <token>" \
  -o export.csv
```

支持组合：

- `source_type=chart` + `export_type=csv`
- `source_type=chart` + `export_type=xlsx`
- `source_type=dashboard` + `export_type=pdf`

## 数据权限

给角色配置数据集访问权限：

```bash
curl -X POST http://localhost:8080/api/resource-permissions \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "resource_type": "dataset",
    "resource_id": 1,
    "subject_type": "role",
    "subject_id": 1,
    "permission_type": "view"
  }'
```

配置行级权限：

```bash
curl -X POST http://localhost:8080/api/data-permission-rules \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "dataset_id": 1,
    "subject_type": "role",
    "subject_id": 1,
    "field_name": "province",
    "operator": "=",
    "value_json": "GD"
  }'
```

隐藏字段：

```bash
curl -X POST http://localhost:8080/api/column-permission-rules \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "dataset_id": 1,
    "subject_type": "user",
    "subject_id": 1,
    "field_name": "amount",
    "permission_type": "hidden"
  }'
```

## 审计与监控

日志查询：

```bash
curl http://localhost:8080/api/operation-logs \
  -H "Authorization: Bearer <token>"

curl http://localhost:8080/api/query-logs \
  -H "Authorization: Bearer <token>"

curl http://localhost:8080/api/login-logs \
  -H "Authorization: Bearer <token>"

curl http://localhost:8080/api/export-logs \
  -H "Authorization: Bearer <token>"
```

健康检查：

```bash
curl http://localhost:8080/api/health
curl http://localhost:8080/api/health/database
curl http://localhost:8080/api/health/redis
curl http://localhost:8080/api/health/storage
curl http://localhost:8080/api/health/queue
```

Prometheus metrics：

```bash
curl http://localhost:8080/api/metrics
```

指标包括：

- `bi_query_total`
- `bi_query_failed_total`
- `bi_query_duration_ms`
- `bi_query_cache_hit_total`
- `bi_import_task_total`
- `bi_export_task_total`
- `bi_queue_pending_jobs`

## 测试说明

测试文件位于 `tests/Feature`，覆盖：

- Auth / RBAC
- DataSource
- Dataset
- Query Engine
- Chart
- Dashboard
- Import
- Export
- Cache
- DataPermission
- AuditLog
- Monitor

运行：

```bash
php artisan test
```

当前通过基线：

```text
81 tests, 720 assertions
```

## 注意事项

- 当前仓库包含 Laravel 后端 API 和 Vue 3 管理端，前端作为 Laravel Vite 资源内置在同一仓库中。
- 图表查询默认启用缓存；如果需要绕过缓存，可在请求中传 `use_cache=false`。
- 导入/导出生产环境应使用 Redis queue，并保证 `queue-worker` 正常运行。
- MinIO bucket 需要存在，否则导入、导出和存储健康检查会失败。
- `/api/health*` 和 `/api/metrics` 是公开接口；如生产环境需要限制访问，应在网关、Nginx 或 Laravel middleware 中添加访问控制。
