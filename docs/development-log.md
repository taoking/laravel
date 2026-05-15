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

## 2026-05-15 架构师面试覆盖度评估与补齐计划

目标：以资深架构师/面试官视角检查当前 Laravel 13 学习项目的知识覆盖度、使用深度和追问风险，并将需要补齐的内容写入文档作为后续执行计划。

### 执行过程

- 检查当前工作区状态：`git status --short --branch`。
- 阅读并抽查：
  - `docs/learning-index.md`
  - `docs/pending-development-tasks.md`
  - `docs/development-completion-review.md`
  - `docs/interview/senior-questions.md`
  - `docs/interview/project-story.md`
  - `docs/laravel-framework-study-guide.md`
  - `docs/laravel-startup-shutdown-flow.md`
- 抽查代码入口：
  - `app/Domains/*`
  - `app/Console/Commands/*`
  - `tests/Feature/*`
- 从面试追问角度评估 PHP 语言、Laravel 源码、MySQL、Redis、Queue/MQ、Kafka、安全、性能、测试、部署等维度。

### 文档变更

- 新增 `docs/interview/architect-interview-coverage-plan.md`：
  - 记录覆盖度评分。
  - 记录面试追问地图。
  - 记录 AIP-01 到 AIP-10 补齐任务。
  - 明确推荐执行顺序和面试通过标准。
- 更新 `docs/learning-index.md`：
  - 增加架构师面试覆盖度计划入口。
- 更新 `docs/pending-development-tasks.md`：
  - 补充架构师面试计划引用。
  - 新增 P1-07 PHP 语言底层代码示例。
  - 新增 P1-08 MySQL 大数据性能实证。
  - 新增 P2-04 安全攻防增强。
  - 调整下一轮推荐执行顺序。

### 验收计划

本次只做计划文档落地，不进行功能代码开发。提交前需要执行：

```bash
git diff --check
git status --short
```

## 2026-05-15 架构师面试复审与任务卡增强

目标：再次以资深架构师/面试官视角复核当前项目的知识覆盖、使用深度和可追问程度，把后续补齐计划细化到后续 agent 可以直接执行的任务卡。

### 执行过程

- 抽查项目路由：`php artisan route:list --except-vendor`，当前显示 43 条项目路由。
- 抽查 Kafka 命令：`php artisan list kafka --raw`，当前显示 5 个 Kafka 命令。
- 抽查代码入口：
  - `routes/api.php`
  - `composer.json`
  - `resources/js/Pages/*`
  - `app/Console/Commands/*`
  - `tests/Feature/Phase*Test.php`
- 抽查专题文档：
  - `docs/interview/architect-interview-coverage-plan.md`
  - `docs/pending-development-tasks.md`
  - `docs/development-completion-review.md`
  - `docs/learning-index.md`
  - `docs/queue/import-export-worker.md`

### 复审结论

- 当前项目功能主线完整，能支撑 Laravel 企业后台、RBAC、指标 API、导入导出、审计、安全、Kafka 和静态分析的面试表达。
- 当前项目下一阶段不应优先扩展普通 CRUD，而应补可靠性、Redis 深度、Laravel 源码、PHP 底层、MySQL 大数据性能、PHP-FPM/Worker/Octane 和 CI/CD。
- 每个后续专题必须同时有代码入口、测试或命令、中文专题文档、生产风险说明和资深追问，只有概念文档不能算完成。

### 文档变更

- 更新 `docs/interview/architect-interview-coverage-plan.md`：
  - 增加本次复审证据。
  - 增加 L1 用法层、L2 原理层、L3 生产层、L4 架构层评估标准。
  - 增加 AIP-01 到 AIP-10 的后续 agent 执行任务卡。
  - 明确每个任务的代码路径、实施路径和验收标准。
- 更新 `docs/pending-development-tasks.md`：
  - 明确后续任务领取时必须先读取架构师计划中的任务卡。
- 更新 `docs/learning-index.md`：
  - 将架构师计划描述调整为“覆盖度评估、追问地图和后续 agent 执行任务卡”。

### 验收计划

本次只增强计划文档，不进行业务代码开发。提交前需要执行：

```bash
git diff --check
git status --short
```

## 2026-05-15 P1-03 MQ 与队列可靠性专题

目标：把当前导入队列扩展为可讲解、可测试、可补偿的 MQ 可靠性案例，补齐 Redis Queue 与 Kafka 边界、失败分类、重复执行和人工补偿追问。

### 开发内容

- 新增迁移 `2026_05_15_000001_add_reliability_fields_to_import_tasks_table.php`：
  - `attempts`
  - `failure_type`
  - `last_failed_at`
  - `compensated_at`
  - `compensation_reason`
- 更新 `ImportTask` 和 `ImportTaskResource`，暴露可靠性字段。
- 更新 `ProcessMetricImportJob`：
  - `completed` 和 `completed_with_errors` 作为终态跳过。
  - Job 开始时记录实际处理次数。
  - 缺文件等 Job 级异常记录 `failure_type`、`last_failed_at` 和 `error_message`。
  - 文件不可读时抛出明确的 `RuntimeException`。
- 更新 `ImportTaskController@retry`：
  - 重试时清理失败分类。
  - 记录 `compensated_at` 和 `compensation_reason=manual retry endpoint`。
- 新增 `ImportCompensateCommand`：
  - 命令：`php artisan imports:compensate`
  - 支持 `--dry-run`、`--id`、`--status`、`--older-than-minutes`、`--limit`、`--clear-failures`。
- 更新 `public/docs/openapi.yaml`，补充 ImportTask 可靠性字段。
- 更新 `docs/queue/import-export-worker.md`：
  - 状态机、幂等策略、失败分类、补偿命令。
  - Redis Queue、RabbitMQ、Kafka 选型对比。
  - 基础问题和资深追问。
- 更新 `docs/pending-development-tasks.md`、`docs/learning-index.md`、`docs/development-completion-review.md`。

### 测试覆盖

新增或增强 `tests/Feature/PhaseFourImportQueueTest.php`：

- 缺文件导致 Job 级失败，记录 `failure_type=storage` 和 `attempts`。
- `completed_with_errors` 终态重复执行跳过。
- `imports:compensate` 可以补偿 failed 任务并重新派发 Job。
- `imports:compensate --dry-run` 不修改任务。
- 原有导入幂等、重试、权限、导出和 OpenAPI 测试继续保留。

### 验收记录

已通过命令：

```bash
php artisan test --filter=PhaseFourImportQueueTest
php artisan list imports --raw
DB_CONNECTION=sqlite DB_DATABASE=/Users/tao/workspace/code/laravel/laravel/database/database.sqlite php artisan imports:compensate --dry-run
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- PhaseFourImportQueueTest 通过：8 个测试、45 个断言。
- 全量测试通过：40 个测试、254 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
- `imports:compensate` 命令已注册，SQLite 演示库 dry-run 输出 `No import tasks matched compensation criteria.`。

## 2026-05-15 P1-01 Redis 缓存专题实验

目标：把项目里的 Redis 使用从普通缓存提升到可追问的缓存可靠性案例，覆盖缓存穿透、击穿、雪崩、热点 Key、分布式锁 token 和 Lua 原子限流。

### 开发内容

- 新增 `MetricCacheService`：
  - 指标详情缓存统一入口。
  - 不存在指标写入短 TTL 空值 payload，防缓存穿透。
  - 正常详情缓存使用随机 TTL，降低雪崩风险。
  - 缓存重建使用 `Cache::lock()`，降低击穿风险。
  - 提供 token lock 获取和释放方法，演示避免误删锁。
- 增强 `MetricController`：
  - 指标详情读取改为走 `MetricCacheService`。
  - 指标更新和删除改为调用缓存服务清理详情缓存。
- 增强 `HotMetricService`：
  - Redis ZSet 热点指标记录后设置随机 TTL。
  - Redis 不可用时继续降级为 Cache。
- 新增 `RedisRateLimiterService`：
  - Redis 可用时使用 Lua 脚本原子执行 `INCR + EXPIRE + 阈值判断`。
  - Redis 不可用时降级为 Cache bucket；Cache store 也不可用时继续降级为进程内 memory bucket，保证本地测试和离线演示稳定。
- 新增 `RedisCacheLabCommand`：
  - 命令：`php artisan redis:cache-lab lua-rate-limit --key=metric-query-demo --limit=3 --decay=60`
- 新增 `docs/redis/cache-reliability.md`：
  - 缓存三大问题、Laravel Cache 与 Redis 原生命令边界、大 Key、热 Key、缓存一致性和面试追问。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`。

### 测试覆盖

新增 `tests/Feature/PhaseEightRedisCacheReliabilityTest.php`：

- 空值缓存防穿透。
- token lock 避免错误 owner 释放锁。
- 指标详情随机 TTL 和更新后缓存失效。
- 热点指标随机 TTL 范围。
- Lua 限流脚本内容、Redis 不可用 fallback 和 Artisan 命令。

### 验收记录

已通过命令：

```bash
php artisan list redis --raw
php artisan redis:cache-lab lua-rate-limit --key=metric-query-demo --limit=3 --decay=60
php artisan test --filter=PhaseEightRedisCacheReliabilityTest
php artisan test --filter=PhaseFiveSecurityAuditTest
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- PhaseEightRedisCacheReliabilityTest 通过：5 个测试、27 个断言。
- PhaseFiveSecurityAuditTest 通过：6 个测试、31 个断言。
- 全量测试通过：45 个测试、281 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
- `redis:cache-lab` 命令已注册，默认 `.env` 下 Redis/MySQL 不可用时仍可通过 memory fallback 输出 `allowed`。

## 2026-05-15 P1-06 Laravel 源码专题文档

目标：把 Laravel 源码理解绑定到当前项目真实入口，让面试表达从“知道概念”提升到“能从项目代码讲到框架核心类和执行链”。

### 文档内容

新增 `docs/laravel-core` 专题目录：

- `container.md`：服务容器、依赖解析、控制器和命令参数注入。
- `service-provider.md`：`register()`、`boot()`、Kafka driver 绑定和 RateLimiter 注册。
- `facade.md`：Facade 静态外观、容器转发、Cache/Redis/Route 使用边界。
- `middleware-pipeline.md`：Middleware 洋葱模型、权限、签名、操作日志和异常返回。
- `router-model-binding.md`：Router、ControllerDispatcher、隐式模型绑定和 route cache。
- `eloquent-query.md`：Eloquent Builder、Query Builder、关系加载、`MetricQuery` 和 N+1 风险。
- `queue-worker.md`：Queue Worker、Job payload、`ProcessMetricImportJob`、重试、timeout 和补偿。

### 验收方式

- 每篇文档都包含：
  - 项目入口。
  - Laravel 源码类。
  - 执行链。
  - 生产风险。
  - 基础问题。
  - 资深追问。
- 已更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md` 和 `docs/development-completion-review.md`。

### 验收记录

本任务只新增文档，不修改业务代码。提交前需要执行：

```bash
git diff --check
```

## 2026-05-15 P1-08 MySQL 大数据性能实证

目标：把指标查询优化从说明推进到可验证的命令和文档证据，覆盖造数、Explain、索引失效、回表/覆盖索引和大数据分页优化。

### 开发内容

- 新增 `SeedMetricDatasetCommand`：
  - 命令：`php artisan metrics:seed-large-dataset`
  - 支持 `--rows`、`--metrics`、`--batch` 和 `--dry-run`。
  - 使用 `MetricValue::upsert()`，避免重复造数产生重复周期数据。
- 新增 `ExplainMetricQueryCommand`：
  - 命令：`php artisan metrics:explain-query`
  - SQLite 使用 `EXPLAIN QUERY PLAN`。
  - MySQL 使用 `EXPLAIN`。
- 新增 `MetricSeekPageCommand`：
  - 命令：`php artisan metrics:seek-page`
  - 演示基于 `id < last_seen_id` 的 seek pagination。
- 新增 `tests/Feature/PhaseNineDatabasePerformanceTest.php`：
  - 验证 dry-run 不写数据。
  - 验证批量造数。
  - 验证 Explain 和 seek pagination 命令可执行。
- 更新 `docs/database/metric-query-explain.md`：
  - Explain 关注点、索引失效、回表、覆盖索引和面试追问。
- 新增 `docs/database/large-pagination.md`：
  - offset pagination 与 seek pagination 对比。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`。

### 验收记录

已通过命令：

```bash
php artisan list metrics --raw
php artisan metrics:seed-large-dataset --rows=10 --metrics=2 --batch=5 --dry-run
php artisan test --filter=PhaseNineDatabasePerformanceTest
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- PhaseNineDatabasePerformanceTest 通过：2 个测试、8 个断言。
- 全量测试通过：47 个测试、289 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
- `metrics:seed-large-dataset`、`metrics:explain-query`、`metrics:seek-page` 命令已注册。

## 2026-05-15 P1-05 多进程、Worker 与 Octane 专题

目标：补齐 PHP 运行机制、常驻进程、FPM、Queue Worker、Scheduler 和 Octane 的面试表达，并提供可运行实验命令。

### 开发内容

- 新增 `RuntimeWorkerLabCommand`：
  - `php artisan runtime:worker-lab memory-growth`
  - `php artisan runtime:worker-lab lifecycle`
- 新增 `docs/runtime/php-fpm-worker-octane.md`：
  - PHP-FPM、CLI、Queue Worker、Scheduler、Octane 生命周期对比。
  - FPM `pm` 模式和进程数估算。
  - Queue Worker 为什么要 `queue:restart`。
  - Scheduler 多机重复执行处理。
  - Octane 常驻内存风险。
  - OPcache、502/504、内存泄漏排障。
- 新增 `tests/Feature/PhaseTenRuntimeProcessTest.php`。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`。

### 验收记录

已通过命令：

```bash
php artisan runtime:worker-lab lifecycle
php artisan test --filter=PhaseTenRuntimeProcessTest
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- PhaseTenRuntimeProcessTest 通过：2 个测试、2 个断言。
- 全量测试通过：49 个测试、291 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
- `runtime:worker-lab` 命令已注册，可用于运行机制演示和面试追问复盘。

## 2026-05-15 P1-07 PHP 语言底层代码示例

目标：把 PHP 语言底层八股转成可运行实验，补齐弱类型、COW、引用、对象赋值、Generator 和 PHP 8.x 新特性的面试证据。

### 开发内容

- 新增 `PhpLanguageLabCommand`：
  - `php artisan php:language-lab weak-types`
  - `php artisan php:language-lab cow`
  - `php artisan php:language-lab references`
  - `php artisan php:language-lab objects`
  - `php artisan php:language-lab generator`
  - `php artisan php:language-lab modern`
  - `php artisan php:language-lab all`
- 新增 `docs/php-language/runtime-labs.md`：
  - PHP 数组、HashTable、Copy-on-Write。
  - 引用与对象赋值差异。
  - Generator 与数组全量加载对比。
  - Union Type、Enum、Readonly、Attribute、Closure、Arrow Function、`#[\Override]`。
  - PHP 7.4 到 PHP 8.4 面试差异表。
- 新增 `tests/Feature/PhaseElevenPhpLanguageLabTest.php`。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`。

### 验收记录

已通过命令：

```bash
php artisan php:language-lab all --rows=10
php artisan php:language-lab cow --rows=10
php artisan test --filter=PhaseElevenPhpLanguageLabTest
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- PhaseElevenPhpLanguageLabTest 通过：3 个测试、13 个断言。
- 全量测试通过：52 个测试、304 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
- `php:language-lab` 命令已注册，可用于语言底层演示和面试追问复盘。

## 2026-05-15 P2-03 GitHub Actions CI

目标：把本地质量门禁固化到 GitHub Actions，避免后续 OpenAPI、安全、Docker 和导入导出增强破坏项目基线。

### 开发内容

- 新增 `.github/workflows/ci.yml`：
  - `composer validate --strict`
  - `composer install`
  - `npm ci`
  - 应用初始化：复制 `.env`、生成 key、创建 SQLite 文件。
  - `composer analyse`
  - `./vendor/bin/pint --test`
  - `php artisan test`
  - `npm run build`
  - `docker compose config`
- 更新 `.github/workflows/tests.yml`：
  - PHP matrix 从 `8.3, 8.4, 8.5` 收敛为 `8.4`，与 `composer.json` 的 `^8.4` 基线一致。
  - 增加 SQLite 数据库文件创建。
- 新增 `docs/testing-ci/github-actions.md`：
  - CI 触发方式。
  - 执行步骤和本地复现命令。
  - 版本基线和失败处理表。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/testing-ci/static-analysis.md`。

### 验收记录

已通过命令：

```bash
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- 全量测试通过：52 个测试、304 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
- CI YAML 已覆盖当前本地质量门禁，旧测试 workflow 已对齐 PHP 8.4 项目基线。

## 2026-05-15 P2-01 OpenAPI 中文化和示例补全

目标：让 `/docs/api` 达到中文演示、接口联调和后续 agent 协作可直接使用的标准。

### 开发内容

- 更新 `public/docs/openapi.yaml`：
  - 顶层说明、tag、operation `summary` 和 `description` 改为中文。
  - 修正路径参数：`{user}`、`{role}`、`{menu}`、`{metric}`、`{import}` 与 Laravel 路由保持一致。
  - 补签名接口 header 说明和请求体示例。
  - 补用户、角色、菜单、指标、导入、导出请求示例。
  - 补 401、403、404、429 和 422 通用错误示例。
  - 补导入、导出幂等键说明。
- 新增 `tests/Feature/PhaseTwelveOpenApiContractTest.php`：
  - 对照 Laravel 路由表校验 `/api/v1` 路径都出现在 OpenAPI。
  - 校验中文说明、错误示例、幂等键和 `trace_id`。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md` 和 `docs/development-completion-review.md`。

### 验收记录

已通过命令：

```bash
php artisan route:list --except-vendor --path=api/v1
ruby -e "require 'yaml'; YAML.load_file('public/docs/openapi.yaml')"
php artisan test --filter=PhaseTwelveOpenApiContractTest
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- API 路由检查显示 `/api/v1` 下 30 条路由。
- PhaseTwelveOpenApiContractTest 通过：2 个测试、40 个断言。
- 全量测试通过：54 个测试、344 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
- OpenAPI YAML 可被 Ruby YAML parser 解析。

## 2026-05-15 P2-04 安全攻防增强

目标：把安全从常规防护说明推进到攻击样例、测试证据和生产风险说明。

### 开发内容

- 新增 SSRF URL 检查：
  - `app/Support/Security/UrlSafetyInspector.php`
  - `app/Http/Controllers/Api/V1/Security/UrlSafetyController.php`
  - `POST /api/v1/security/url-check`
- 新增审计 metadata 脱敏：
  - `app/Support/Security/SensitiveDataMasker.php`
  - `App\Listeners\WriteAuditLog` 写入 metadata 前递归脱敏。
- 新增 `docs/security/web-attack-labs.md`：
  - SSRF、XSS、SQL 注入、CSRF、反序列化、文件上传、审计脱敏、签名反重放。
  - 攻击路径、防护方式、生产补强和资深追问。
- 更新 `docs/security/security-audit.md`。
- 更新 `public/docs/openapi.yaml`，补 `POST /api/v1/security/url-check`。
- 新增 `tests/Feature/PhaseThirteenSecurityAttackLabTest.php`：
  - SSRF 本地和私有地址拦截。
  - 公网 HTTP(S) 目标允许。
  - SQL 注入样式 keyword 作为普通数据处理。
  - XSS payload 在指标描述中被拦截。
  - 审计 metadata 敏感字段脱敏。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`。

### 验收记录

已通过命令：

```bash
php artisan test --filter=PhaseThirteenSecurityAttackLabTest
php artisan test --filter=PhaseTwelveOpenApiContractTest
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
```

验收结果：

- PhaseThirteenSecurityAttackLabTest 通过：5 个测试、15 个断言。
- PhaseTwelveOpenApiContractTest 通过：2 个测试、41 个断言。
- 全量测试通过：59 个测试、360 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验和 Docker Compose 配置校验均通过。

## 2026-05-15 P3-04 Docker 一键启动验收

目标：把 Docker 从静态配置推进到可一键运行、可验收、可排障的部署证据。

### 开发内容

- 更新 `docker/php/Dockerfile`：
  - 安装 Node/npm，支持容器内 `npm ci` 和 `npm run build`。
  - 通过 PECL 安装并启用 `redis` 扩展，使 Docker 环境与 `REDIS_CLIENT=phpredis` 一致。
- 更新 `docker-compose.yml`：
  - 为 `app`、`nginx`、`mysql`、`redis` 增加 healthcheck。
  - 为依赖服务增加 `depends_on.condition: service_healthy`。
  - 为运行态服务增加 `restart: unless-stopped`，保证 `queue:restart` 后 Worker 可被重新拉起。
  - 为 `app` 增加 `node-modules` 命名卷，避免容器 `npm ci` 覆盖宿主机原生依赖。
- 新增 `scripts/deploy/docker-smoke.sh`：
  - 执行 `docker compose config`、`up -d --build`、composer、npm build、key、migrate、seed、queue restart。
  - 配置容器内 Git `safe.directory /var/www/html`，避免 bind mount 所有权提示干扰验收日志。
  - 在 HTTP 验收前强制重建 Nginx 并对 `/up`、`/login`、`/docs/api`、`/api/v1/health` 做重试检查。
  - 在最终 `docker compose ps` 前等待 `app`、`nginx`、`mysql`、`redis` health 状态稳定。
  - Kafka topic 优先通过宿主机 `KAFKA_DRIVER=docker php artisan kafka:topics --create` 验证项目封装；不可用时退回 Kafka 容器 CLI。
- 更新 `docker/nginx/default.conf`：
  - 使用 Docker DNS `127.0.0.11` 和变量形式 `fastcgi_pass` 动态解析 `app:9000`，避免 app 容器重建后 Nginx 指向旧 IP。
  - 验证 `/login`、`/docs/api`、`/api/v1/health`。
- 更新 `docs/deploy/docker-deploy-runbook.md`：
  - 补 smoke 用法、访问路径、日志命令、发布回滚命令和 502/504/MySQL/Redis/Kafka/权限排障表。
- 新增 `tests/Feature/PhaseFourteenDockerRunbookTest.php`：
  - 校验 Compose 配置、smoke 脚本关键步骤和部署 Runbook 排障路径。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`。

### 验收记录

已通过命令：

```bash
scripts/deploy/docker-smoke.sh
php artisan test --filter=PhaseFourteenDockerRunbookTest
docker compose config
```

真实 smoke 结果：

- PhaseFourteenDockerRunbookTest 通过：5 个测试、39 个断言。
- `composer install`、`npm ci`、`npm run build`、`migrate --seed`、`queue:restart` 均通过。
- Kafka topic 创建通过，输出 `Kafka topics ready: 6`。
- `/login`、`/docs/api`、`/api/v1/health` 均可访问。
- 最终 `docker compose ps` 显示 `app`、`nginx`、`mysql`、`redis`、`queue`、`scheduler`、`kafka` 均为运行态，其中 `app`、`nginx`、`mysql`、`redis` 为 healthy。
- 全量测试通过：64 个测试、399 个断言。

## 2026-05-15 P2-02 测试覆盖增强

目标：把阶段验收测试补强为模块级回归保护网，覆盖认证、授权、验证错误、资源不存在和关键失败路径副作用。

### 开发内容

- 新增 `tests/Feature/PhaseFifteenRegressionCoverageTest.php`：
  - 未登录访问用户、指标、导入、导出、审计等关键 API，统一返回 401 JSON。
  - 管理员创建用户和指标时覆盖重复邮箱、非法分类、XSS payload、重复编码和非法状态的 422 合同。
  - 分析师角色只允许读取指标，不允许写用户、角色、指标、导入、导出或审计。
  - 无效导出类型返回 422，且不会创建 `export_tasks`。
  - 不存在指标资源返回统一 404 JSON。
- 新增 `docs/testing-ci/regression-coverage.md`：
  - 记录后续新增 API、权限、异步任务和缓存测试的最低规则。
  - 补 Feature Test 与 Unit Test 边界、权限角色覆盖、422 合同固定和 Mock/Fake/集成测试取舍。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`：
  - 将 P2-02 标记为已完成。
  - 将下一优先级统一调整为 P3-02 大数据导出异步化。

### 验收记录

已通过命令：

```bash
php artisan test --filter=PhaseFifteenRegressionCoverageTest
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- PhaseFifteenRegressionCoverageTest 通过：5 个测试、99 个断言。
- 全量测试通过：69 个测试、498 个断言。
- `composer analyse`、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。

## 2026-05-15 P3-02 大数据导出异步化

目标：把导出任务从“只创建记录”补齐为后台生成 CSV、可查询进度、可鉴权下载的完整闭环。

### 开发内容

- 新增 `database/migrations/2026_05_15_000002_add_progress_fields_to_export_tasks_table.php`：
  - 为 `export_tasks` 增加 `total_rows`、`processed_rows`、`file_size`、`attempts`、`failure_type`、`last_failed_at` 和 `downloaded_at`。
- 新增 `app/Jobs/ProcessMetricExportJob.php`：
  - 使用 `chunkById(500)` 分批读取指标。
  - 将 CSV 写入临时文件，再通过 Storage stream 保存到私有磁盘。
  - 记录总行数、已处理行数、文件大小、尝试次数和失败分类。
  - 失败时删除可能存在的半成品文件。
- 更新 `app/Http/Controllers/Api/V1/Imports/ExportTaskController.php`：
  - `GET /api/v1/exports` 返回当前用户导出任务列表。
  - `POST /api/v1/exports` 创建任务并派发导出 Job，继续支持 `Idempotency-Key`。
  - `GET /api/v1/exports/{export}` 返回状态、进度、文件大小、错误原因和下载地址。
  - `GET /api/v1/exports/{export}/download` 校验任务创建者、完成状态和文件存在性后下载 CSV。
- 更新 `app/Http/Resources/Imports/ExportTaskResource.php`：
  - 输出 `progress_percentage`、`download_url`、`file_size`、`failure_type` 和时间字段。
- 更新 `public/docs/openapi.yaml`：
  - 补导出列表、详情、下载接口，新增 `ExportId` 参数和导出进度字段。
- 更新 `docs/queue/import-export-worker.md`：
  - 补导出状态机、进度字段、内存控制策略、下载鉴权和资深追问。
- 新增 `tests/Feature/PhaseSixteenAsyncExportTest.php`：
  - 覆盖 CSV 生成、进度、文件大小、详情查询、下载、幂等、非创建者禁止下载、未完成下载 409 和磁盘异常失败分类。
- 更新 `docs/pending-development-tasks.md`、`docs/interview/architect-interview-coverage-plan.md`、`docs/learning-index.md`、`docs/development-completion-review.md` 和 `docs/implementation-execution-plan.md`。

### 验收记录

已通过命令：

```bash
php artisan test --filter=PhaseSixteenAsyncExportTest
php artisan test --filter=PhaseTwelveOpenApiContractTest
ruby -e "require 'yaml'; YAML.load_file('public/docs/openapi.yaml')"
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

验收结果：

- PhaseSixteenAsyncExportTest 通过：4 个测试、33 个断言。
- PhaseTwelveOpenApiContractTest 通过：2 个测试、44 个断言。
- 全量测试通过：73 个测试、534 个断言。
- `composer analyse`、OpenAPI YAML 解析、`npm run build`、Pint、Composer 校验、Docker Compose 配置校验和 diff 检查均通过。
