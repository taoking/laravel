# Laravel 13 指标分析平台执行计划

本文是后续 Codex agent 开发时的执行依据。它把学习架构转成可落地的任务、目录约定、访问路径、接口路径、验收标准和完成定义。

## 1. 固定决策

- 框架：Laravel 13。
- PHP：8.4 作为主运行版本。
- 产品：多数据源指标分析与内容管理平台。
- 前端形态：Inertia 管理后台。
- 界面语言：默认中文 `zh-CN`，通过 `resources/js/i18n.js` 支持 `en-US` 切换。
- API 风格：`/api/v1` 前缀的 JSON API。
- 接口文档：项目内置静态 OpenAPI + Swagger UI，不依赖外部 SaaS。
- 接口文档访问路径：
  - Swagger UI：`http://127.0.0.1:8000/docs/api`
  - OpenAPI YAML：`http://127.0.0.1:8000/docs/openapi.yaml`
- 后台访问路径：
  - 首页重定向：`http://127.0.0.1:8000`
  - 登录页：`http://127.0.0.1:8000/login`
  - 管理后台：`http://127.0.0.1:8000/admin`
- API 根路径：
  - `http://127.0.0.1:8000/api/v1`
- 健康检查：
  - Laravel 默认健康检查：`http://127.0.0.1:8000/up`
  - API 健康检查：`http://127.0.0.1:8000/api/v1/health`

## 2. 目标交付物

最终项目应能作为资深 PHP 面试项目展示，必须具备：

- 可本地启动的 Docker / 本机 PHP 运行说明。
- Inertia 后台基础页面。
- 前端多语言闭环：默认中文、可切换英文、语言偏好持久化。
- RBAC 权限闭环：用户、角色、权限、菜单、按钮权限、数据范围。
- 指标管理闭环：指标库、分类、维度、查询、分页、筛选、排序。
- 异步任务闭环：CSV/Excel 导入、失败记录、重试、幂等、定时统计。
- 缓存与限流闭环：权限缓存、指标缓存、首页统计缓存、接口限流、分布式锁。
- 静态分析闭环：PHPStan/Larastan/Psalm、`composer analyse`、规则级别说明和后续提升路径。
- Kafka 事件流专题闭环：Topic、Producer、Consumer、message key、partition 顺序性、consumer group、offset、幂等表、失败重试、dead letter topic、lag 观察和 Redis Queue 边界说明。
- 安全闭环：CSRF、XSS、SQL 注入防护、越权测试、接口签名、文件上传安全。
- 性能闭环：慢 SQL、慢接口、Explain 案例、压测入口、OPcache/FPM 调优说明。
- 部署闭环：Nginx、PHP-FPM、Queue Worker、Scheduler、Supervisor、健康检查、日志和回滚说明。
- 面试闭环：每个模块对应学习笔记、代码入口、测试入口和面试问答。

## 3. 目录和代码组织约定

业务代码按模块组织，避免控制器和模型散落导致后续难以复盘。

```text
app/
  Domains/
    Access/
    Metrics/
    Imports/
    Audit/
    Files/
  Http/
    Controllers/
      Web/
      Api/V1/
    Requests/
    Resources/
    Middleware/
  Jobs/
  Events/
  Listeners/
  Policies/
  Services/
  Support/
database/
  migrations/
  seeders/
  factories/
resources/
  js/
    Pages/
    Components/
    Layouts/
routes/
  web.php
  api.php
docs/
  api/
  implementation-execution-plan.md
  learning-index.md
```

约定：

- Controller 只处理请求编排，不写复杂业务。
- FormRequest 负责验证和基础授权。
- Application Service 处理用例流程。
- Domain Service 处理领域规则。
- Query Object 处理复杂查询条件。
- Resource 负责 API 输出结构。
- Policy / Middleware 负责权限和安全边界。
- Event / Listener / Observer 负责审计和副作用。
- Job 必须幂等，所有外部副作用要可重试。

## 4. 路由与访问路径规划

### 4.1 Web / Inertia 路由

| Method | Path | 用途 | 鉴权 |
| --- | --- | --- | --- |
| GET | `/` | 重定向到 `/admin` 或 `/login` | 自动判断 |
| GET | `/login` | 登录页 | guest |
| POST | `/login` | 登录提交 | guest + rate limit |
| POST | `/logout` | 退出登录 | auth |
| GET | `/admin` | 后台首页 | auth |
| GET | `/admin/users` | 用户管理 | auth + permission |
| GET | `/admin/roles` | 角色管理 | auth + permission |
| GET | `/admin/menus` | 菜单管理 | auth + permission |
| GET | `/admin/metrics` | 指标管理 | auth + permission |
| GET | `/admin/imports` | 导入任务管理 | auth + permission |
| GET | `/admin/audit-logs` | 审计日志 | auth + permission |
| GET | `/docs/api` | Swagger UI | 本地默认公开，生产可加 auth |

### 4.2 API 路由

统一前缀：`/api/v1`。

统一响应结构：

```json
{
  "success": true,
  "data": {},
  "message": "OK",
  "trace_id": "string"
}
```

分页响应结构：

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100
  },
  "message": "OK",
  "trace_id": "string"
}
```

错误响应结构：

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {},
  "trace_id": "string"
}
```

核心 API：

| Method | Path | 用途 |
| --- | --- | --- |
| GET | `/api/v1/health` | API 健康检查 |
| GET | `/api/v1/me` | 当前用户信息 |
| GET | `/api/v1/permissions` | 当前用户权限和菜单 |
| GET | `/api/v1/permissions/catalog` | 权限和菜单目录 |
| GET | `/api/v1/users` | 用户列表 |
| POST | `/api/v1/users` | 创建用户 |
| GET | `/api/v1/users/{id}` | 用户详情 |
| PUT | `/api/v1/users/{id}` | 更新用户 |
| DELETE | `/api/v1/users/{id}` | 删除用户 |
| GET | `/api/v1/roles` | 角色列表 |
| POST | `/api/v1/roles` | 创建角色 |
| PUT | `/api/v1/roles/{id}` | 更新角色 |
| DELETE | `/api/v1/roles/{id}` | 删除非系统角色 |
| PUT | `/api/v1/roles/{id}/permissions` | 分配权限 |
| PUT | `/api/v1/menus/{id}` | 更新菜单排序和可见性 |
| GET | `/api/v1/metrics` | 指标列表，支持筛选排序分页 |
| POST | `/api/v1/metrics` | 创建指标 |
| GET | `/api/v1/metrics/{id}` | 指标详情 |
| PUT | `/api/v1/metrics/{id}` | 更新指标 |
| DELETE | `/api/v1/metrics/{id}` | 删除指标 |
| GET | `/api/v1/metric-categories` | 指标分类 |
| GET | `/api/v1/dimensions/regions` | 地区维度 |
| GET | `/api/v1/dimensions/frequencies` | 频率维度 |
| POST | `/api/v1/imports` | 创建导入任务 |
| GET | `/api/v1/imports` | 导入任务列表 |
| GET | `/api/v1/imports/{id}` | 导入任务详情 |
| POST | `/api/v1/imports/{id}/retry` | 重试导入任务 |
| POST | `/api/v1/exports` | 创建导出任务 |
| GET | `/api/v1/audit-logs` | 审计日志列表 |

### 4.3 Artisan 命令入口

| Command | 用途 |
| --- | --- |
| `php artisan metrics:daily-summary` | 指标日常统计检查 |
| `php artisan imports:compensate --dry-run` | 导入任务补偿预检查 |
| `php artisan kafka:topics --create` | 创建或查看 Kafka topic |
| `php artisan kafka:produce metric.import.completed` | 生产导入完成事件 |
| `php artisan kafka:consume audit-log-consumer` | 消费事件并执行业务 handler |
| `php artisan kafka:lag audit-log-consumer` | 查看 consumer group lag |
| `php artisan kafka:dead-letter:replay metrics.data.changed.dlq` | replay 死信消息 |

## 5. 数据模型规划

首批核心表：

- `users`：Laravel 默认用户表，补充状态字段。
- `roles`：角色。
- `permissions`：权限点，包含菜单、按钮、API 权限。
- `menus`：后台菜单树。
- `role_user`：用户角色。
- `permission_role`：角色权限。
- `metrics`：指标主表。
- `metric_categories`：指标分类。
- `metric_values`：指标数据值。
- `regions`：地区维度。
- `frequencies`：频率维度。
- `import_tasks`：导入任务。
- `import_failures`：导入失败明细。
- `export_tasks`：导出任务。
- `audit_logs`：审计日志。
- `operation_logs`：操作日志。
- `uploaded_files`：上传文件记录。

关键约束：

- 所有业务表使用 `id` 主键、`created_at`、`updated_at`。
- 需要软删除的表使用 `deleted_at`。
- 状态字段使用 Enum 映射。
- 导入、导出、支付类或外部回调类任务必须有幂等键。
- 高频查询字段必须在迁移中明确索引。
- 审计日志不可依赖前端传入用户信息，必须从认证上下文和请求上下文生成。

## 6. 分阶段实施路径

### Phase 0：文档与基线确认

目标：后续开发 agent 能直接按文档执行。

任务：

- 完成 `docs/learning-index.md`。
- 完成 `docs/laravel13-senior-php-learning-architecture.md`。
- 完成 `docs/learning-knowledge-map.md`。
- 完成本文档 `docs/implementation-execution-plan.md`。

验收：

- `rg -n "访问路径|接口文档|验收标准|Phase 1|/api/v1|/docs/api" docs`
- `git status --short` 只出现 docs 文档改动。

### Phase 1：环境、Inertia 骨架、统一规范

目标：项目能启动，后台能访问，API 有统一响应和文档入口。

任务：

- 配置 PHP 8.4 运行说明和本地启动命令。
- 安装并配置 Inertia 前端栈。
- 建立后台基础布局：侧边栏、顶部栏、内容区、空状态、加载状态。
- 建立 `/login`、`/admin` 页面。
- 建立 `/api/v1/health`。
- 建立统一 JSON 响应 helper 或 Resource 基类。
- 建立异常响应规范，返回 `trace_id`。
- 建立 Swagger UI 页面 `/docs/api`。
- 建立静态 OpenAPI 文件 `/docs/openapi.yaml`。

验收：

- `php artisan test` 通过。
- `npm run build` 通过。
- `GET /up` 返回成功。
- `GET /api/v1/health` 返回统一 JSON。
- 浏览器可访问 `/login`、`/admin`、`/docs/api`。
- `/docs/openapi.yaml` 可被 Swagger UI 加载。

当前实现状态：

- 状态：已完成首版实现。
- 代码入口：
  - `routes/web.php`
  - `routes/api.php`
  - `bootstrap/app.php`
  - `app/Support/ApiResponse.php`
  - `app/Http/Middleware/EnsureTraceId.php`
  - `app/Http/Middleware/HandleInertiaRequests.php`
  - `resources/views/app.blade.php`
  - `resources/views/docs/api.blade.php`
  - `resources/js/Layouts/AdminLayout.vue`
  - `resources/js/Pages/Auth/Login.vue`
  - `resources/js/Pages/Dashboard.vue`
  - `public/docs/openapi.yaml`
- 测试入口：
  - `tests/Feature/PhaseOneScaffoldTest.php`
  - `tests/Feature/ExampleTest.php`
- 已验证命令：
  - `php artisan test`
  - `npm run build`
  - `php artisan route:list --path=docs --path=api`
- 已验证访问路径：
  - `http://127.0.0.1:8000/up`
  - `http://127.0.0.1:8000/login`
  - `http://127.0.0.1:8000/admin`
  - `http://127.0.0.1:8000/api/v1/health`
  - `http://127.0.0.1:8000/docs/api`
  - `http://127.0.0.1:8000/docs/openapi.yaml`

### Phase 2：Auth、RBAC、菜单和权限

目标：完成后台权限系统闭环。

任务：

- 实现登录、退出、当前用户接口。
- 实现用户、角色、权限、菜单迁移、模型、Seeder。
- 实现超级管理员种子账号。
- 实现菜单树和按钮权限。
- 实现 Policy / Middleware 权限校验。
- 实现权限缓存和缓存刷新。
- 实现防水平越权测试。

验收：

- 未登录访问 `/admin` 跳转 `/login`。
- 登录后访问 `/admin` 成功。
- 普通用户不能访问无权限菜单和 API。
- 超级管理员拥有全部权限。
- `GET /api/v1/permissions` 返回菜单树和权限码。
- Feature Test 覆盖登录、权限通过、权限拒绝、越权拒绝。

当前实现状态：

- 状态：已完成首版实现。
- 登录与退出：
  - `GET /login`
  - `POST /login`
  - `POST /logout`
- 后台页面入口：
  - `GET /admin`
  - `GET /admin/users`
  - `GET /admin/roles`
  - `GET /admin/menus`
  - `GET /admin/metrics`
- API 入口：
  - `GET /api/v1/me`
  - `GET /api/v1/permissions`
  - `GET /api/v1/users`
  - `POST /api/v1/users`
  - `GET /api/v1/users/{user}`
  - `PUT /api/v1/users/{user}`
  - `DELETE /api/v1/users/{user}`
  - `GET /api/v1/roles`
  - `POST /api/v1/roles`
  - `PUT /api/v1/roles/{role}`
  - `PUT /api/v1/roles/{role}/permissions`
- 代码入口：
  - `database/migrations/2026_05_14_000001_add_status_to_users_table.php`
  - `database/migrations/2026_05_14_000002_create_access_control_tables.php`
  - `database/seeders/AccessControlSeeder.php`
  - `app/Domains/Access/Models/Role.php`
  - `app/Domains/Access/Models/Permission.php`
  - `app/Domains/Access/Models/Menu.php`
  - `app/Domains/Access/Services/PermissionService.php`
  - `app/Http/Controllers/AuthSessionController.php`
  - `app/Http/Controllers/Api/V1/AccessController.php`
  - `app/Http/Controllers/Api/V1/UserController.php`
  - `app/Http/Controllers/Api/V1/RoleController.php`
  - `app/Http/Middleware/EnsureUserHasPermission.php`
  - `app/Policies/UserPolicy.php`
- 种子账号：
  - 超级管理员：`admin@example.com` / `password`
  - 分析师用户：`analyst@example.com` / `password`
- 测试入口：
  - `tests/Feature/PhaseTwoAccessControlTest.php`
- 已覆盖场景：
  - 未登录访问 `/admin` 跳转 `/login`。
  - 未登录访问 `/api/v1/me` 返回统一 JSON 401。
  - 超级管理员登录并访问 `/admin`。
  - `GET /api/v1/me` 返回当前用户。
  - `GET /api/v1/permissions` 返回权限码和菜单树。
  - 普通用户访问无权限 API 返回 403。
  - 普通用户只能查看自己，不能查看其他用户详情。
  - 角色权限更新后刷新受影响用户的权限缓存。

### Phase 3：指标库、维度和查询 API

目标：完成指标管理核心业务。

任务：

- 实现指标分类、指标主表、指标值、地区、频率维度。
- 实现指标 CRUD。
- 实现多条件查询：分类、地区、频率、时间范围、关键字。
- 实现分页、排序、字段白名单。
- 实现 Query Object，避免控制器堆查询逻辑。
- 实现 Eager Loading，避免 N+1。
- 建立 Explain 示例文档和慢 SQL 示例。

验收：

- `/admin/metrics` 可查看指标列表。
- `GET /api/v1/metrics` 支持筛选、排序、分页。
- 非白名单排序字段被拒绝。
- 指标详情不会产生明显 N+1。
- Feature Test 覆盖 CRUD、筛选、排序、分页、权限。
- 文档记录至少一个 Explain 示例。

当前实现状态：

- 状态：已完成首版实现。
- API 入口：
  - `GET /api/v1/metrics`
  - `POST /api/v1/metrics`
  - `GET /api/v1/metrics/{metric}`
  - `PUT /api/v1/metrics/{metric}`
  - `DELETE /api/v1/metrics/{metric}`
  - `GET /api/v1/metric-categories`
  - `GET /api/v1/dimensions/regions`
  - `GET /api/v1/dimensions/frequencies`
- 代码入口：
  - `database/migrations/2026_05_14_000003_create_metric_tables.php`
  - `database/seeders/MetricSeeder.php`
  - `app/Domains/Metrics/Models/Metric.php`
  - `app/Domains/Metrics/Models/MetricCategory.php`
  - `app/Domains/Metrics/Models/MetricValue.php`
  - `app/Domains/Metrics/Models/Region.php`
  - `app/Domains/Metrics/Models/Frequency.php`
  - `app/Domains/Metrics/Queries/MetricQuery.php`
  - `app/Http/Controllers/Api/V1/Metrics/MetricController.php`
  - `app/Http/Controllers/Api/V1/Metrics/MetricCategoryController.php`
  - `app/Http/Controllers/Api/V1/Metrics/DimensionController.php`
- 文档入口：
  - `docs/database/metric-query-explain.md`
- 测试入口：
  - `tests/Feature/PhaseThreeMetricManagementTest.php`
- 已覆盖场景：
  - 指标列表按关键字、分类、地区、频率筛选。
  - 指标列表分页并返回 `meta`。
  - 非白名单排序字段返回 422。
  - `metrics.view` 用户可查询指标和维度。
  - 无 `metrics.manage` 用户不能创建指标。
  - 超级管理员可创建、查看、更新、删除指标。
  - OpenAPI 覆盖 Phase 3 指标接口。

### Phase 4：导入、导出、队列和定时任务

目标：完成异步任务能力闭环。

任务：

- 实现 CSV 导入任务。
- 预留 Excel 导入接口，首期可先只支持 CSV。
- 导入任务写入 `import_tasks`。
- 失败行写入 `import_failures`。
- Job 使用幂等键，支持重试。
- 大文件按 chunk / LazyCollection 处理。
- 实现导出任务 `export_tasks`。
- 实现定时统计 Command。
- 使用 Schedule 注册统计和清理任务。
- 文档化 Supervisor / Worker 启动命令。

验收：

- `POST /api/v1/imports` 创建导入任务。
- Worker 执行后导入数据入库。
- 错误行进入失败记录。
- `POST /api/v1/imports/{id}/retry` 可重试失败任务。
- 同一个幂等键重复提交不会重复导入。
- 大文件导入不一次性读入内存。
- Queue Job Unit/Feature Test 通过。

当前实现状态：

- 状态：已完成首版实现。
- API 入口：
  - `POST /api/v1/imports`
  - `GET /api/v1/imports`
  - `GET /api/v1/imports/{import}`
  - `POST /api/v1/imports/{import}/retry`
  - `POST /api/v1/exports`
- 后台页面入口：
  - `GET /admin/imports`
- 代码入口：
  - `database/migrations/2026_05_14_000004_create_import_export_tables.php`
  - `app/Domains/Imports/Models/ImportTask.php`
  - `app/Domains/Imports/Models/ImportFailure.php`
  - `app/Domains/Imports/Models/ExportTask.php`
  - `app/Domains/Files/Models/UploadedFile.php`
  - `app/Http/Controllers/Api/V1/Imports/ImportTaskController.php`
  - `app/Http/Controllers/Api/V1/Imports/ExportTaskController.php`
  - `app/Jobs/ProcessMetricImportJob.php`
  - `app/Console/Commands/ImportCompensateCommand.php`
  - `app/Console/Commands/ComputeMetricDailySummary.php`
  - `routes/console.php`
- 文档入口：
  - `docs/queue/import-export-worker.md`
- 测试入口：
  - `tests/Feature/PhaseFourImportQueueTest.php`
- 已覆盖场景：
  - CSV 导入创建任务并写入指标值。
  - 错误行写入 `import_failures`。
  - 同一个幂等键重复提交不会创建重复任务。
  - 导入任务可重试。
  - 无导入/导出权限用户被拒绝。
  - 导出任务幂等创建。
  - 导入 Job 记录尝试次数、失败分类和最近失败时间。
  - `imports:compensate` 支持 dry-run 和补偿 failed 任务。
  - `metrics:daily-summary` 命令可执行。

### Phase 5：缓存、限流、安全和审计

目标：项目具备生产级安全和性能基础。

任务：

- 权限缓存。
- 指标详情缓存。
- 首页统计缓存。
- 热门指标排行榜 ZSet。
- 登录限流。
- 查询 API 限流。
- 防重复提交。
- 接口签名和反重放 Middleware。
- 文件上传校验、存储和访问控制。
- 审计日志 Event / Listener。
- 操作日志 Middleware。
- XSS、SQL 注入、越权测试。

验收：

- 权限变更后缓存可刷新。
- 缓存命中和未命中路径都有测试。
- 限流超限返回 429。
- 非法签名请求被拒绝。
- 上传非法类型文件被拒绝。
- 审计日志记录用户、动作、资源、IP、trace_id。
- 安全 Feature Test 覆盖 CSRF、越权、签名、上传。

当前实现状态：

- 状态：已完成首版实现。
- API 入口：
  - `POST /api/v1/security/signed-echo`
  - `GET /api/v1/audit-logs`
- 代码入口：
  - `database/migrations/2026_05_14_000005_create_audit_logs_table.php`
  - `app/Domains/Audit/Models/AuditLog.php`
  - `app/Events/AuditEvent.php`
  - `app/Listeners/WriteAuditLog.php`
  - `app/Http/Middleware/VerifyApiSignature.php`
  - `app/Http/Middleware/RecordOperationLog.php`
  - `app/Domains/Operations/Models/OperationLog.php`
  - `app/Domains/Metrics/Services/HotMetricService.php`
  - `app/Providers/AppServiceProvider.php`
  - `app/Http/Controllers/Api/V1/Audit/AuditLogController.php`
- 文档入口：
  - `docs/security/security-audit.md`
- 测试入口：
  - `tests/Feature/PhaseFiveSecurityAuditTest.php`
- 已覆盖场景：
  - 指标详情缓存命中。
  - 首页统计缓存命中。
  - 热点指标排行记录。
  - 指标更新后缓存失效。
  - 指标更新写入审计日志，包含用户、动作、资源。
  - API 请求写入操作日志。
  - 超级管理员可查询审计日志。
  - 指标查询超限返回 429。
  - 签名缺失返回 401。
  - 重放请求返回 409。
  - 非法导入文件类型返回 422。
  - 指标写入拒绝 `<script>` 输入。

### Phase 6：性能、部署和面试包装

目标：形成可展示、可讲解、可部署的资深面试项目。

任务：

- 编写压测脚本和压测说明。
- 记录慢接口和慢 SQL 示例。
- 编写 PHP-FPM、OPcache、Nginx 调优文档。
- 编写 Docker Compose 部署方案。
- 编写 Nginx + PHP-FPM + Supervisor 配置样例。
- 编写发布、回滚、故障排查 Runbook。
- 整理面试题库和项目包装话术。

验收：

- `docs/` 中有性能优化案例。
- `docs/` 中有安全加固案例。
- `docs/` 中有部署和回滚 Runbook。
- `docs/` 中有面试项目介绍。
- 可说清一个完整请求、一个导入任务、一个权限校验、一个慢 SQL 优化案例。

当前实现状态：

- 状态：已完成首版实现。
- 性能入口：
  - `docs/performance/performance-runbook.md`
  - `scripts/bench/wrk-metrics.sh`
  - `docs/database/metric-query-explain.md`
- 部署入口：
  - `docker-compose.yml`
  - `docker/php/Dockerfile`
  - `docker/php/opcache.ini`
  - `docker/nginx/default.conf`
  - `docker/supervisor/worker.conf`
  - `docs/deploy/docker-deploy-runbook.md`
- 面试入口：
  - `docs/interview/project-story.md`
  - `docs/interview/senior-questions.md`
- 已覆盖内容：
  - Nginx + PHP-FPM + MySQL + Redis + Queue Worker + Scheduler。
  - OPcache 配置样例。
  - Supervisor Worker 样例。
  - 发布、回滚、502/504 排查。
  - wrk 压测脚本。
  - 项目包装话术和资深追问。

## 7. API 文档规范

接口文档采用静态 OpenAPI 文件和内置 Swagger UI。

规划文件：

```text
public/docs/openapi.yaml
resources/views/docs/api.blade.php
routes/web.php
```

访问路径：

- `/docs/api`：Swagger UI 页面。
- `/docs/openapi.yaml`：OpenAPI YAML。

OpenAPI 必须覆盖：

- Auth。
- 当前用户。
- 权限菜单。
- 用户管理。
- 角色权限。
- 指标管理。
- 指标查询。
- 导入任务。
- 导出任务。
- 审计日志。
- 健康检查。

每个接口至少包含：

- Method。
- Path。
- Summary。
- Tags。
- Request parameters / body。
- Response 200。
- Response 401 / 403 / 422。
- 示例 JSON。

验收：

- 浏览器打开 `/docs/api` 能看到 Swagger UI。
- Swagger UI 能加载 `/docs/openapi.yaml`。
- OpenAPI 中所有已实现 API 都有记录。
- API 文档路径写入 `docs/learning-index.md`。

## 8. 测试与质量门禁

每个开发阶段都必须满足：

```bash
composer analyse
php artisan test
./vendor/bin/pint --test
npm run build
```

静态分析可拆分执行：

```bash
composer analyse:phpstan
composer analyse:psalm
```

测试覆盖最低要求：

- Auth / RBAC：Feature Test。
- 指标查询：Feature Test + 查询条件单元测试。
- 导入任务：Job Test + 幂等测试。
- 安全：越权、限流、签名、上传测试。
- 缓存：命中、未命中、刷新测试。
- API 文档：文档路径访问测试。

## 9. 完成定义

一个阶段只有同时满足以下条件才算完成：

- 代码实现完成。
- 对应文档更新。
- 对应 API 文档更新。
- 测试通过。
- 访问路径可用。
- 关键失败场景被测试覆盖。
- `git status --short` 中没有无意文件。

一个主题只有同时满足以下条件才算完成：

- 有明确源码入口。
- 有明确访问路径或命令入口。
- 有测试。
- 有中文说明。
- 有面试题和资深追问。
- 有生产风险说明。

## 10. 后续 Agent 执行规则

后续 Codex agent 执行开发任务时必须遵守：

- 先读 `docs/implementation-execution-plan.md`。
- 再读 `docs/learning-index.md`。
- 只实现当前阶段，不跨阶段扩散。
- 每次新增 API 都同步更新 OpenAPI。
- 每次新增页面都同步更新访问路径说明。
- 每次新增业务能力都补测试和面试说明。
- 不引入未经说明的大型依赖。
- 不把学习示例和生产业务逻辑混在同一个无边界类里。
