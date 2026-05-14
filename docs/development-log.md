# 开发日志

## 2026-05-14 P0 后台真实数据联动

目标：基于 `docs/pending-development-tasks.md` 中 P0 待开发项，完成后台页面真实 API 联动、补齐必要接口、更新文档并完成验收。

### 开发内容

- 新增前端 API 客户端：`resources/js/api.js`。
- 用户管理页接入 `/api/v1/users` 和 `/api/v1/roles`，支持分页、筛选、创建、编辑、删除和角色分配。
- 角色管理页接入 `/api/v1/roles` 和 `/api/v1/permissions/catalog`，支持角色 CRUD 和权限分配。
- 菜单管理页接入权限目录，支持树形展示、排序值、可见状态编辑。
- 指标管理页接入 `/api/v1/metrics`、`/api/v1/metric-categories`、`/api/v1/dimensions/frequencies`，支持筛选、分页、创建、编辑、删除。
- 导入任务页接入 `/api/v1/imports`，支持 CSV 上传、幂等键、任务列表和失败重试。
- 审计日志页接入 `/api/v1/audit-logs`，支持动作和用户筛选。
- 新增权限目录接口：`GET /api/v1/permissions/catalog`。
- 新增菜单更新接口：`PUT /api/v1/menus/{menu}`。
- 新增角色删除接口：`DELETE /api/v1/roles/{role}`。
- 更新 OpenAPI：补充权限目录、菜单更新、角色删除接口。
- 更新多语言文案：补充表单、按钮、状态、字段和提示语。

### 验收记录

已通过命令：

```bash
npm run build
php artisan test --filter=PhaseTwoAccessControlTest
php artisan test
./vendor/bin/pint --test
composer validate --strict
docker compose config
php artisan route:list --path=api/v1/permissions
php artisan route:list --path=api/v1/menus
```

验收结果：

- 前端生产构建通过。
- PhaseTwoAccessControlTest 通过：10 个测试、90 个断言。
- 全量测试通过：34 个测试、213 个断言。
- Pint 格式检查通过。
- Composer 配置校验通过。
- Docker Compose 配置校验通过。
- 新增权限目录、菜单更新、角色删除路由均已注册。

### 后续任务

- P1-02：接入 PHPStan/Larastan/Psalm 静态分析。
- P1-03：强化 MQ 与队列可靠性专题。
- P1-04：按 `docs/queue/kafka-practice.md` 实现 Kafka 消息事件流实践模块。

## 2026-05-14 P1-02 静态分析基线与优先级调整

目标：把 PHPStan/Larastan/Psalm 提升为 P1 工程质量基线，并把 Kafka 使用专题调整为静态分析完成后的下一项高优先级开发任务。

### 开发内容

- 安装 `larastan/larastan` 和 `vimeo/psalm`。
- 新增 `phpstan.neon`，接入 Larastan extension，分析 `app`、`routes`、`tests`。
- 新增 `psalm.xml`，以 PHP 8.4 和低风险级别建立 Psalm 基线。
- 在 `composer.json` 增加 `analyse`、`analyse:phpstan`、`analyse:psalm`。
- 为 Laravel Resource 增加模型 `@mixin`，让静态分析能识别 Eloquent 字段访问。
- 为覆盖父类的方法增加 `#[\Override]`，包括 Model `casts()`、Resource `toArray()`、Inertia middleware、ServiceProvider 和测试基类。
- 为 `routes/console.php` 中 Laravel 闭包命令的 `$this` 绑定增加局部 Psalm 说明。
- 新增 `docs/testing-ci/static-analysis.md`，记录工具定位、命令、基线、准入门禁和后续提升路径。
- 更新 `docs/pending-development-tasks.md`：P1-02 标记已完成，P1-04 Kafka 调整为下一项高优先级任务。
- 更新 `docs/queue/kafka-practice.md`：补充 Kafka 执行优先级、准入门禁和建议开发顺序。

### 验收记录

已通过命令：

```bash
composer analyse
composer analyse:phpstan
composer analyse:psalm
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
php artisan route:list --except-vendor
git diff --check
```

验收结果：

- PHPStan/Larastan 通过，错误数 0。
- Psalm 通过，错误数 0。
- 全量测试通过：34 个测试、213 个断言。
- 前端生产构建通过。
- Pint 格式检查、Composer 严格校验、Docker Compose 配置校验、diff 空白检查均通过。
- 项目路由注册正常：`php artisan route:list --except-vendor` 显示 43 条路由。
- 后续 Kafka、Redis、队列和接口任务提交前必须保持 `composer analyse` 通过。

## 2026-05-14 P1-04 Kafka 消息事件流实践模块

目标：把 Kafka 从概念对比提升为 Laravel 13 项目中的消息事件流实践模块，覆盖事件发布、消费、consumer group、幂等、失败重试、dead letter、lag 观察和 Redis Queue 边界说明。

### 开发内容

- `docker-compose.yml` 新增单节点 Kafka 服务。
- 新增 `config/kafka.php`，配置 driver、broker、topic、consumer group、重试和 dead letter topic。
- 新增 `app/Domains/Messaging`：
  - `KafkaMessage`、`KafkaRecord` 消息协议对象。
  - `KafkaProducer` 和 `KafkaConsumerService`。
  - `LocalKafkaClient` 文件型测试驱动和 `DockerKafkaClient` Docker Kafka CLI 驱动。
  - `AuditLogEventHandler` 和 `MetricCacheRefreshHandler`。
  - `ConsumedMessage` 幂等消费模型。
- 新增迁移：`consumed_messages`，通过 `consumer_group + idempotency_key` 约束重复消费。
- 新增 Artisan 命令：
  - `kafka:topics`
  - `kafka:produce`
  - `kafka:consume`
  - `kafka:lag`
  - `kafka:dead-letter:replay`
- 接入真实业务事件：
  - `ProcessMetricImportJob` 导入完成后发布 `metric.import.completed`。
  - `MetricController` 指标新增、更新、删除后发布 `metric.data.changed`。
  - `WriteAuditLog` 审计日志写入后发布 `audit.event.created`，发布失败只 report，不阻断主业务。
- 新增 `tests/Feature/PhaseSevenKafkaMessagingTest.php`，覆盖生产消费、审计日志副作用、幂等跳过、缓存刷新、失败记录和 dead letter。
- 更新 `docs/queue/kafka-practice.md`、`docs/pending-development-tasks.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 Docker runbook。

### 验收记录

已通过命令：

```bash
composer analyse
php artisan test
php artisan test --filter=PhaseSevenKafkaMessagingTest
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
php artisan list kafka --raw
php artisan kafka:topics --create
php artisan kafka:lag audit-log-consumer
docker compose up -d kafka
KAFKA_DRIVER=docker php artisan kafka:topics --create
KAFKA_DRIVER=docker DB_CONNECTION=sqlite DB_DATABASE=/Users/tao/workspace/code/laravel/laravel/database/database.sqlite php artisan kafka:produce metric.import.completed --payload='{"import_task_id":9001,"status":"completed","total_rows":2,"success_rows":2,"failed_rows":0}' --idempotency-key=metric-import:9001:completed:v1 --trace-id=trace-docker-kafka
KAFKA_DRIVER=docker DB_CONNECTION=sqlite DB_DATABASE=/Users/tao/workspace/code/laravel/laravel/database/database.sqlite php artisan kafka:consume audit-log-consumer --max=1 --timeout-ms=10000
git diff --check
```

验收结果：

- Kafka 专项测试通过：3 个测试、19 个断言。
- 全量测试通过：37 个测试、232 个断言。
- 静态分析、前端构建、Pint、Composer 校验、Docker Compose 配置校验均通过。
- Kafka 命令已注册 5 个。
- 本地 driver 可创建 6 个 topic / dead letter topic。
- 本地 lag 命令可输出 `audit-log-consumer` 的 topic lag。
- Docker Kafka 容器启动成功，`KAFKA_DRIVER=docker` 可创建 topic、生产消息并消费写入审计日志。
