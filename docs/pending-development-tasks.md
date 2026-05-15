# 待开发任务清单

更新日期：2026-05-15
适用分支：`13.x`  
执行基线：`docs/development-completion-review.md`  
主计划：`docs/implementation-execution-plan.md`
架构师面试补齐计划：`docs/interview/architect-interview-coverage-plan.md`

本文档记录首轮 Phase 1 到 Phase 6 完成后的后续开发任务。后续 Codex agent 可以按优先级领取任务，每个任务完成后必须同步更新本文档状态、相关接口文档和验收记录。

当前优先级调整：

- PHPStan/Larastan/Psalm 已提升为 P1 工程质量基线，后续每个开发任务都要先保证 `composer analyse` 可通过。
- Kafka 使用专题已完成 P1 最小事件流闭环。
- 资深架构师/面试官覆盖度评估已写入 `docs/interview/architect-interview-coverage-plan.md`，该文档现在包含后续 agent 可直接执行的任务卡；MQ 可靠性和 Redis 深度已完成，后续补齐重点转为 Laravel 源码、MySQL 性能实证、PHP 运行机制和 CI/CD。

## 1. 优先级定义

| 优先级 | 定义 | 处理顺序 |
| --- | --- | --- |
| P0 | 影响项目可演示性的核心任务 | 下一轮优先开发 |
| P1 | 资深面试高频能力建设任务，包含 Kafka 实践和静态分析基线 | P0 完成后优先推进 |
| P2 | 工程质量、接口文档、测试和 CI/CD | 与 P0/P1 穿插推进 |
| P3 | 产品增强、扩展专题和加分模块 | 基础闭环稳定后推进 |

## 2. 任务执行规则

每个任务完成时必须满足：

- 有明确代码入口：页面、API、Artisan 命令、Job、测试或脚本。
- 有中文说明：记录核心概念、业务场景、生产风险和面试表达。
- 有验收方式：自动化测试、命令输出、接口响应、日志、截图或文档。
- 有资深追问：至少 5 个基础问题和 5 个资深追问，写入对应专题文档。
- 有架构边界：说明为什么这样设计、替代方案是什么、生产风险在哪里。
- 前端页面必须接入 `resources/js/i18n.js`，默认中文显示。
- 新增 API 必须同步更新 `public/docs/openapi.yaml`。
- 新增页面必须同步更新 `docs/learning-index.md` 和本文档。
- 涉及安全、性能、队列、缓存、部署的任务，必须补充对应专题文档。

状态值：

| 状态 | 含义 |
| --- | --- |
| 待开发 | 尚未开始 |
| 开发中 | 已开始但未验收 |
| 已完成 | 代码、测试和文档均完成 |
| 暂缓 | 有依赖或当前阶段不适合推进 |

## 3. P0 后台真实数据联动

### P0-01 用户管理页面数据联动

- 状态：已完成
- 目标：让 `/admin/users` 从结构占位变成可演示的用户管理页面。
- 建议范围：
  - `resources/js/Pages/Access/Users.vue`
  - `app/Http/Controllers/Api/V1/UserController.php`
  - `public/docs/openapi.yaml`
  - `tests/Feature/PhaseTwoAccessControlTest.php` 或新增测试文件
- 功能要求：
  - 接入 `/api/v1/users` 列表。
  - 支持分页、关键字搜索、状态筛选。
  - 支持创建、编辑、禁用或删除用户。
  - 表单错误、空状态、加载状态、成功提示均使用中文多语言文案。
- 验收标准：
  - 管理员登录后可以查看种子用户。
  - 分析师账号无法越权执行管理操作。
  - Feature Test 覆盖列表、创建、更新、删除和越权场景。
  - `npm run build`、`php artisan test` 通过。

### P0-02 角色与权限分配页面数据联动

- 状态：已完成
- 目标：让 `/admin/roles` 支持角色 CRUD 和权限分配。
- 建议范围：
  - `resources/js/Pages/Access/Roles.vue`
  - `app/Http/Controllers/Api/V1/RoleController.php`
  - RBAC 模型和权限服务
  - `public/docs/openapi.yaml`
- 功能要求：
  - 接入 `/api/v1/roles`。
  - 支持角色新增、编辑、删除。
  - 支持为角色分配权限。
  - 权限变更后清理或刷新权限缓存。
- 验收标准：
  - 超级管理员可完成权限分配闭环。
  - 权限缓存刷新后 `/api/v1/permissions` 返回最新菜单和权限。
  - 测试覆盖权限更新、缓存刷新和越权。

### P0-03 菜单管理树形展示

- 状态：已完成
- 目标：让 `/admin/menus` 展示真实菜单树和按钮权限。
- 建议范围：
  - `resources/js/Pages/Access/Menus.vue`
  - 菜单模型、Seeder、权限服务
  - `public/docs/openapi.yaml`
- 功能要求：
  - 展示菜单层级、路径、权限标识、可见状态。
  - 支持菜单排序和启用/禁用。
  - 区分目录、菜单、按钮权限。
- 验收标准：
  - 管理员能看到完整菜单树。
  - 不同角色登录后导航菜单不同。
  - 菜单变更后权限缓存失效并重建。

### P0-04 指标管理页面数据联动

- 状态：已完成
- 目标：让 `/admin/metrics` 成为真实指标管理界面。
- 建议范围：
  - `resources/js/Pages/Metrics/Index.vue`
  - `app/Http/Controllers/Api/V1/Metrics/MetricController.php`
  - 指标 Request、Resource、Query Object
  - `docs/database/metric-query-explain.md`
- 功能要求：
  - 接入 `/api/v1/metrics`。
  - 支持分类、地区、频率、状态、关键字筛选。
  - 支持排序、分页、详情、新增、编辑。
  - 列表展示缓存命中或接口耗时信息，便于讲性能优化。
- 验收标准：
  - 页面能展示种子指标。
  - 筛选条件能影响接口查询。
  - 测试覆盖排序白名单、分页、详情缓存和越权。

### P0-05 导入任务页面数据联动

- 状态：已完成
- 目标：让 `/admin/imports` 支持上传、查看状态和失败重试。
- 建议范围：
  - `resources/js/Pages/Imports/Index.vue`
  - `app/Http/Controllers/Api/V1/Imports/ImportTaskController.php`
  - `app/Jobs/ProcessMetricImportJob.php`
  - `docs/queue/import-export-worker.md`
- 功能要求：
  - 上传 CSV 文件并创建导入任务。
  - 展示任务状态、总行数、成功行数、失败行数。
  - 支持失败任务重试。
  - 展示幂等键或重复提交处理结果。
- 验收标准：
  - 成功上传测试 CSV 后生成导入任务。
  - Worker 执行后任务状态变化可查询。
  - 重复上传或重复提交不会造成重复数据。

### P0-06 审计日志页面数据联动

- 状态：已完成
- 目标：让 `/admin/audit-logs` 支持真实日志查询和筛选。
- 建议范围：
  - `resources/js/Pages/Audit/Index.vue`
  - `app/Http/Controllers/Api/V1/Audit/AuditLogController.php`
  - `docs/security/security-audit.md`
- 功能要求：
  - 接入 `/api/v1/audit-logs`。
  - 支持按用户、动作、资源、时间范围筛选。
  - 展示 `trace_id`，可用于排查请求链路。
- 验收标准：
  - 操作用户、指标、导入任务后能看到审计记录。
  - 非管理员无法查看审计日志。
  - 测试覆盖筛选条件和权限边界。

## 4. P1 资深面试实验模块

当前 P1 执行顺序：

1. P1-02 静态分析基线：已完成，作为后续开发准入门禁。
2. P1-04 Kafka 使用专题实验：已完成，提供 Kafka 事件流最小闭环。
3. P1-03 MQ 与队列可靠性专题：已完成，导入队列具备失败分类、尝试次数、终态幂等和补偿命令。
4. P1-01 Redis 缓存专题实验：已完成，指标缓存具备空值缓存、随机 TTL、token lock 和 Lua 限流实验。
5. P1-06 Laravel 源码专题文档：已完成，Container、Provider、Facade、Middleware、Router、Eloquent、Queue Worker 均绑定项目入口。
6. P1-08 MySQL 大数据性能实证：已完成，具备造数、Explain 和 seek pagination 命令。
7. P1-05 多进程、Worker 与 Octane 专题：已完成，具备 runtime 实验命令和运行机制文档。
8. P1-07 PHP 语言底层代码示例：已完成，具备 COW、引用、Generator、Enum、Attribute 等可运行实验。

### P1-01 Redis 缓存专题实验

- 状态：已完成
- 目标：通过指标业务演示缓存穿透、击穿、雪崩、热点 Key、大 Key、分布式锁和 Lua 限流。
- 建议范围：
  - `app/Domains/Metrics/Services`
  - `app/Http/Middleware`
  - `docs/security` 或新建 `docs/redis`
  - 对应 Feature Test
- 功能要求：
  - 指标详情缓存加随机 TTL。
  - 空结果缓存防穿透。
  - 热点指标排行榜使用 ZSet。
  - 导入任务使用分布式锁。
  - 限流增加 Lua 示例或文档说明。
- 验收标准：
  - 文档能解释 Laravel Cache 和 Redis 直接操作的差异。
  - 测试覆盖缓存命中、空缓存、锁释放和限流。
- 完成记录：
  - 已新增 `MetricCacheService`，覆盖指标详情空值缓存、随机 TTL、重建锁和 token lock 释放。
  - 已增强 `HotMetricService`，热点指标使用 Redis ZSet，测试环境降级为 Cache，并增加随机 TTL。
  - 已新增 `RedisRateLimiterService` 和 `redis:cache-lab lua-rate-limit`，演示 Lua 原子限流。
  - 已新增 `docs/redis/cache-reliability.md`。
  - 已新增 `tests/Feature/PhaseEightRedisCacheReliabilityTest.php`，覆盖穿透、击穿、雪崩、锁 owner 和 Lua 限流 fallback。

### P1-02 静态分析基线：PHPStan、Larastan、Psalm

- 状态：已完成
- 目标：把 PHPStan/Larastan/Psalm 提升为高优先级工程质量任务，为后续模块开发提供类型和静态规则约束。
- 建议范围：
  - `composer.json`
  - `phpstan.neon` 或 `phpstan.neon.dist`
  - `psalm.xml` 或 `psalm.xml.dist`
  - `app/`、`tests/`
  - `docs/learning-index.md`
  - 新增 `docs/testing-ci/static-analysis.md`
- 功能要求：
  - 安装并配置 Larastan/PHPStan。
  - 安装并配置 Psalm，先以可落地的低风险级别接入。
  - 增加 Composer scripts，例如 `analyse`、`analyse:phpstan`、`analyse:psalm`。
  - 为 Eloquent 模型、Collection、DTO、Service 返回值补充必要类型标注。
  - 明确 PHPStan/Larastan 与 Psalm 的职责差异：Laravel 语义检查、泛型/类型推导、死代码和接口约束。
  - 将静态分析接入后续 CI 任务。
- 验收标准：
  - 本地可执行 PHPStan/Larastan 分析命令。
  - 本地可执行 Psalm 分析命令。
  - 新增文档解释规则级别、已知忽略项、后续提升路径和面试表达。
  - 不允许为了通过检查而大面积降低类型约束或屏蔽真实问题。
- 完成记录：
  - 已安装 `larastan/larastan` 和 `vimeo/psalm`。
  - 已新增 `phpstan.neon`、`psalm.xml` 和 `docs/testing-ci/static-analysis.md`。
  - 已新增 Composer scripts：`analyse`、`analyse:phpstan`、`analyse:psalm`。
  - 已通过 `composer analyse:phpstan` 和 `composer analyse:psalm`。

### P1-03 MQ 与队列可靠性专题

- 状态：已完成
- 目标：把当前导入队列扩展为可讲解的 MQ 可靠性案例。
- 建议范围：
  - `app/Jobs/ProcessMetricImportJob.php`
  - Import/Export 模型
  - `docs/queue/import-export-worker.md`
- 功能要求：
  - 明确 Job 幂等键。
  - 记录失败原因和重试次数。
  - 增加任务补偿命令。
  - 文档对比 Redis Queue、RabbitMQ、Kafka 的适用场景，并和 P1-04 Kafka 实践任务建立引用。
- 验收标准：
  - 测试覆盖失败、重试、重复执行和补偿。
  - 文档能回答“消息丢失、重复消费、顺序性、死信”的面试问题。
- 完成记录：
  - `import_tasks` 已增加 `attempts`、`failure_type`、`last_failed_at`、`compensated_at` 和 `compensation_reason`。
  - `ProcessMetricImportJob` 已将 `completed`、`completed_with_errors` 作为终态跳过，并记录 Job 级失败分类。
  - 已新增 `php artisan imports:compensate`，支持 `--dry-run`、`--id`、`--status`、`--older-than-minutes`、`--limit` 和 `--clear-failures`。
  - 已更新 `docs/queue/import-export-worker.md`，补充 Redis Queue、RabbitMQ、Kafka 选型和可靠性追问。
  - `tests/Feature/PhaseFourImportQueueTest.php` 已覆盖缺文件失败、终态重复执行、补偿命令和 dry-run。

### P1-04 Kafka 使用专题实验

- 状态：已完成
- 当前优先级：P1 高，已完成最小可验收事件流闭环；P1-03 已继续补强 MQ 可靠性专题。
- 目标：把 Kafka 从概念对比提升为 Laravel 13 项目中的消息事件流实践模块，既能本地运行，又能覆盖事件驱动、幂等、顺序性、消费者组、offset、失败补偿、死信和面试表达。
- 执行计划：`docs/queue/kafka-practice.md`。
- 建议范围：
  - `docker-compose.yml`
  - `docker/`
  - 新增 `app/Domains/Messaging`
  - 新增 `config/kafka.php`
  - 新增 Kafka Producer/Consumer 服务类和事件 Handler
  - 新增 `consumed_messages` 消费幂等表
  - 新增 Artisan 生产者、消费者、Topic、Lag、死信补偿命令
  - 新增 Feature Test 或集成测试说明
- 功能要求：
  - 本地 Docker 增加 Kafka 单节点开发环境。
  - 设计三个业务事件：`metric.import.completed`、`metric.data.changed`、`audit.event.created`。
  - 每个事件必须说明 topic、message key、payload、headers、version、created_at、trace_id、producer、consumer、幂等键和失败处理策略。
  - 实现 Producer：发布指标导入完成、指标数据变更和审计事件。
  - 实现 Consumer：消费事件并写入审计日志、刷新缓存或更新统计表。
  - 设计 message key 和 partition 策略，说明同一 `metric_id` 局部有序和热点 key 风险。
  - 设计 `consumed_messages` 幂等表，说明重复消息跳过、业务成功后提交 offset、提交 offset 失败后的重复消费处理。
  - 设计重试、dead letter topic、dead letter payload、人工补偿和重新消费死信消息。
  - 文档说明 offset、consumer group、ack、rebalance、lag、死信、幂等、顺序性。
  - 保留 Redis Queue 与 Kafka 的差异说明：Redis Queue 偏任务队列，Kafka 偏事件流、日志流和跨系统事件分发。
- 验收标准：
  - `docker compose up -d` 后能启动 Kafka。
  - 能通过命令创建或查看 topic。
  - `php artisan kafka:produce metric.import.completed` 能生产消息。
  - `php artisan kafka:consume audit-log-consumer` 能消费消息。
  - 消费后数据库审计日志新增一条记录。
  - 消费同一消息多次不会产生重复业务副作用。
  - 消费失败时能记录失败状态，超过最大重试次数后进入 dead letter topic。
  - 消费者组内启动两个消费者时，同一 partition 不会被两个消费者同时消费。
  - 文档能回答 Kafka 与 Redis Queue 的区别、Kafka 顺序性、offset、consumer group、ack、rebalance、lag、死信、幂等和数据库事务与消息发送一致性。
- 完成记录：
  - 已新增 Kafka 单节点 Docker 服务：`docker-compose.yml` 中的 `kafka`。
  - 已新增配置：`config/kafka.php`。
  - 已新增领域模块：`app/Domains/Messaging`。
  - 已新增消费幂等表：`consumed_messages`。
  - 已新增命令：`kafka:topics`、`kafka:produce`、`kafka:consume`、`kafka:lag`、`kafka:dead-letter:replay`。
  - 已接入真实业务事件：导入完成、指标数据变更、审计事件创建。
  - 已新增测试：`tests/Feature/PhaseSevenKafkaMessagingTest.php`，覆盖生产、消费、审计副作用、幂等、缓存刷新、失败和死信。

### P1-05 多进程、Worker 与 Octane 专题

- 状态：已完成
- 目标：补齐 PHP 多进程、常驻进程、FPM、Queue Worker 和 Octane/Swoole 的面试表达。
- 建议范围：
  - 新增 `docs/runtime`
  - 新增 Artisan 示例命令
  - `docker/supervisor/worker.conf`
- 功能要求：
  - 增加 PHP CLI 长任务示例。
  - 增加 Worker 平滑重启说明。
  - 文档解释 PHP-FPM 和常驻 Worker 的内存泄漏风险。
  - 文档对比 Octane 与传统 FPM 请求模型。
- 验收标准：
  - 有可运行 Artisan 命令。
  - 文档包含进程模型图或流程说明。
  - 能回答“为什么 Worker 更新代码后要 restart”。
- 完成记录：
  - 已新增 `runtime:worker-lab memory-growth` 和 `runtime:worker-lab lifecycle`。
  - 已新增 `docs/runtime/php-fpm-worker-octane.md`。
  - 已新增 `tests/Feature/PhaseTenRuntimeProcessTest.php`。

### P1-06 Laravel 源码专题文档

- 状态：已完成
- 目标：围绕当前项目补齐 Laravel 核心源码学习材料。
- 建议范围：
  - `docs/laravel-framework-study-guide.md`
  - 新增 `docs/laravel-core`
- 功能要求：
  - Container 依赖解析。
  - ServiceProvider 的 register/boot。
  - Facade 解析流程。
  - Middleware Pipeline 洋葱模型。
  - Router 到 Controller 的调用链。
  - Eloquent 查询构造和关系加载。
  - Queue Worker 执行流程。
- 验收标准：
  - 每篇文档绑定一个项目代码入口。
  - 每篇文档包含基础问题和资深追问。
- 完成记录：
  - 已新增 `docs/laravel-core/container.md`。
  - 已新增 `docs/laravel-core/service-provider.md`。
  - 已新增 `docs/laravel-core/facade.md`。
  - 已新增 `docs/laravel-core/middleware-pipeline.md`。
  - 已新增 `docs/laravel-core/router-model-binding.md`。
  - 已新增 `docs/laravel-core/eloquent-query.md`。
  - 已新增 `docs/laravel-core/queue-worker.md`。

### P1-07 PHP 语言底层代码示例

- 状态：已完成
- 目标：把 PHP 语言底层八股转成可运行实验，支撑资深 PHP 面试追问。
- 执行计划：`docs/interview/architect-interview-coverage-plan.md` 中的 AIP-04。
- 建议范围：
  - 新增 `app/Console/Commands/PhpLanguageLabCommand.php` 或 `tests/Unit/PhpLanguageFeatureTest.php`
  - 新增 `docs/php-language/runtime-labs.md`
- 功能要求：
  - 演示 PHP 数组、弱类型、引用、写时复制、对象赋值、闭包、Generator。
  - 演示 PHP 8.x 关键特性：Union Type、Enum、Readonly、Attribute、`#[\Override]`。
  - 记录内存变化、输出结果和面试表达。
- 验收标准：
  - 有可运行命令或测试。
  - 文档能回答 COW 什么时候触发、Generator 如何降低内存、PHP 8.x 特性解决什么问题。
- 完成记录：
  - 已新增 `php:language-lab` 命令，支持 `weak-types`、`cow`、`references`、`objects`、`generator`、`modern` 和 `all`。
  - 已新增 `docs/php-language/runtime-labs.md`。
  - 已新增 `tests/Feature/PhaseElevenPhpLanguageLabTest.php`。

### P1-08 MySQL 大数据性能实证

- 状态：已完成
- 目标：把指标查询优化从说明推进到可验证的 Explain、慢 SQL 和分页优化证据。
- 执行计划：`docs/interview/architect-interview-coverage-plan.md` 中的 AIP-05。
- 建议范围：
  - 新增大数据 Seeder 或 Artisan 造数命令。
  - 更新 `docs/database/metric-query-explain.md`。
  - 新增 `docs/database/large-pagination.md`。
- 功能要求：
  - 支持生成 10 万到 100 万条指标值测试数据。
  - 记录关键查询的 Explain 前后对比。
  - 演示普通分页、游标分页、ID seek pagination 或覆盖索引优化。
  - 记录慢 SQL、索引失效和排序优化案例。
- 验收标准：
  - 有造数命令或 Seeder。
  - 有 Explain 输出样例。
  - 文档能回答最左前缀、覆盖索引、回表、索引下推、千万级分页优化。
- 完成记录：
  - 已新增 `metrics:seed-large-dataset`，支持 `--rows`、`--metrics`、`--batch` 和 `--dry-run`。
  - 已新增 `metrics:explain-query`，按数据库 driver 输出 SQLite `EXPLAIN QUERY PLAN` 或 MySQL `EXPLAIN`。
  - 已新增 `metrics:seek-page`，演示 ID seek pagination。
  - 已更新 `docs/database/metric-query-explain.md`，补 Explain 关注点、索引失效、回表和覆盖索引。
  - 已新增 `docs/database/large-pagination.md`。
  - 已新增 `tests/Feature/PhaseNineDatabasePerformanceTest.php`。

## 5. P2 工程质量、接口文档和 CI/CD

### P2-01 OpenAPI 中文化和示例补全

- 状态：已完成
- 目标：让接口文档可直接用于开发、调试和面试演示。
- 建议范围：
  - `public/docs/openapi.yaml`
  - `resources/views/docs/api.blade.php`
- 功能要求：
  - 所有接口补中文摘要和说明。
  - 补请求示例、成功响应示例、错误响应示例。
  - 补鉴权、限流、签名接口说明。
- 验收标准：
  - `/docs/api` 可直接查看完整接口说明。
  - 新增或修改接口后 YAML 与路由保持一致。
- 完成记录：
  - 已将 `public/docs/openapi.yaml` 主体说明、标签、接口摘要和接口描述改为中文。
  - 已补签名、导入、导出、用户、角色、菜单、指标等请求示例和通用错误示例。
  - 已修正 path 参数名，与 Laravel 路由 `{user}`、`{role}`、`{menu}`、`{metric}`、`{import}` 保持一致。
  - 已新增 `tests/Feature/PhaseTwelveOpenApiContractTest.php`，对照路由表校验 `/api/v1` 路径覆盖。

### P2-02 测试覆盖增强

- 状态：已完成
- 目标：把当前测试从阶段验收扩展为模块级回归保障。
- 建议范围：
  - `tests/Feature`
  - `tests/Unit`
  - `tests/TestCase.php`
- 功能要求：
  - API CRUD 覆盖成功和失败场景。
  - 权限测试覆盖管理员、分析师、未登录用户。
  - 队列测试覆盖 fake、失败和重试。
  - 缓存测试覆盖命中、失效和一致性。
- 验收标准：
  - `php artisan test` 稳定通过。
  - 测试名称能体现业务行为，而不是只验证状态码。
- 完成记录：
  - 已新增 `tests/Feature/PhaseFifteenRegressionCoverageTest.php`。
  - 覆盖未登录关键 API 统一 401 JSON、管理员 422 验证合同、分析师只读权限矩阵、无效导出不落库和 404 统一错误结构。
  - 已新增 `docs/testing-ci/regression-coverage.md`，说明后续新增 API、权限、队列、缓存测试规则和面试表达。
  - 已通过 `php artisan test --filter=PhaseFifteenRegressionCoverageTest`，5 个测试、99 个断言。
  - 已通过全量 `php artisan test`，69 个测试、498 个断言。
  - 已通过 `composer analyse`，保持 PHPStan/Larastan/Psalm 基线。

### P2-03 GitHub Actions CI

- 状态：已完成
- 目标：补齐持续集成，避免后续任务破坏项目。
- 建议范围：
  - `.github/workflows/ci.yml`
- 功能要求：
  - Composer validate。
  - PHP 依赖安装。
  - Pint 检查。
  - PHPUnit。
  - npm install 和 npm run build。
  - docker compose config。
- 验收标准：
  - 推送或 PR 时自动运行。
  - CI 文档写入 `docs/deploy/docker-deploy-runbook.md` 或新增 CI 文档。
- 完成记录：
  - 已新增 `.github/workflows/ci.yml`。
  - 已更新 `.github/workflows/tests.yml`，PHP matrix 与当前 `composer.json` 的 `^8.4` 基线一致。
  - 已新增 `docs/testing-ci/github-actions.md`。

### P2-04 安全攻防增强

- 状态：已完成
- 目标：把安全从“防护说明”推进到“攻击样例 + 测试 + 生产风险说明”。
- 执行计划：`docs/interview/architect-interview-coverage-plan.md` 中的 AIP-07。
- 建议范围：
  - `docs/security/security-audit.md`
  - 新增 `docs/security/web-attack-labs.md`
  - 对应 Feature Test
- 功能要求：
  - 补充 SSRF、XSS、CSRF、SQL 注入、反序列化、敏感字段脱敏说明。
  - 补充文件上传仅校验扩展名的风险说明。
  - 补充敏感操作二次确认或签名增强方案。
- 验收标准：
  - 测试覆盖越权、签名、上传、SSRF/XSS 基础边界。
  - 文档包含攻击路径、防护方式、Laravel 相关机制和资深追问。
- 完成记录：
  - 已新增 SSRF URL 检查接口 `POST /api/v1/security/url-check`。
  - 已新增 `UrlSafetyInspector`、`SensitiveDataMasker` 和审计 metadata 脱敏。
  - 已新增 `docs/security/web-attack-labs.md`。
  - 已新增 `tests/Feature/PhaseThirteenSecurityAttackLabTest.php`。

## 6. P3 产品增强和加分模块

### P3-01 Excel 导入

- 状态：已完成
- 目标：在 CSV 导入基础上增加 Excel 导入能力。
- 建议范围：
  - 导入 Controller、Request、Job
  - 文件上传校验
  - `docs/queue/import-export-worker.md`
- 功能要求：
  - 支持 `.xlsx` 文件。
  - 复用导入任务和失败记录模型。
  - 保持幂等和失败重试能力。
- 验收标准：
  - Excel 示例文件可导入。
  - 非法文件类型会被拒绝并记录审计日志。
- 完成记录：
  - 已新增依赖 `openspout/openspout`，用于 `.xlsx` 流式读取。
  - 已新增 `app/Domains/Imports/Readers/MetricImportReader.php`，统一封装 CSV/XLSX 行读取。
  - `POST /api/v1/imports` 已支持 `.csv`、`.txt` 和 `.xlsx`，并拒绝旧 `.xls` 文件。
  - XLSX 导入复用 `import_tasks`、`import_failures`、幂等键、重试接口和队列 Job。
  - 已更新 `resources/js/Pages/Imports/Index.vue` 文件选择类型和 `public/docs/openapi.yaml`。
  - 已新增 `tests/Feature/PhaseSeventeenExcelImportTest.php`，覆盖 XLSX 成功导入、失败行、幂等、重试、非法 XLS 拒绝和 OpenAPI。
  - 已通过 `php artisan test --filter=PhaseSeventeenExcelImportTest`，4 个测试、23 个断言。
  - 已通过 `php artisan test --filter=PhaseFourImportQueueTest`，8 个测试、45 个断言。
  - 已通过全量 `php artisan test`，77 个测试、559 个断言。
  - 已通过 `composer analyse`，保持 PHPStan/Larastan/Psalm 基线。

### P3-02 大数据导出异步化

- 状态：已完成
- 目标：让导出任务具备真实文件生成和下载鉴权。
- 建议范围：
  - ExportTask 模型和 Controller
  - 新增导出 Job
  - Storage 配置
- 功能要求：
  - 创建导出任务。
  - 后台异步生成 CSV。
  - 支持查询进度和下载。
  - 下载时校验权限。
- 验收标准：
  - 大量数据导出不阻塞请求。
  - 文档能解释如何避免内存爆掉。
- 完成记录：
  - 已新增 `app/Jobs/ProcessMetricExportJob.php`，后台分批查询指标并生成 CSV 文件。
  - `export_tasks` 已增加 `total_rows`、`processed_rows`、`file_size`、`attempts`、`failure_type`、`last_failed_at` 和 `downloaded_at`。
  - 已新增 `GET /api/v1/exports`、`GET /api/v1/exports/{export}` 和 `GET /api/v1/exports/{export}/download`。
  - 下载接口校验任务创建者、任务完成状态和文件存在性。
  - 已更新 `public/docs/openapi.yaml` 和 `docs/queue/import-export-worker.md`。
  - 已新增 `tests/Feature/PhaseSixteenAsyncExportTest.php`，覆盖生成 CSV、进度、幂等、下载鉴权、未完成下载拒绝和失败分类。
  - 已通过 `php artisan test --filter=PhaseSixteenAsyncExportTest`，4 个测试、33 个断言。
  - 已通过 `php artisan test --filter=PhaseTwelveOpenApiContractTest`，2 个测试、44 个断言。
  - 已通过全量 `php artisan test`，73 个测试、534 个断言。
  - 已通过 `composer analyse`，保持 PHPStan/Larastan/Psalm 基线。

### P3-03 首页统计真实化

- 状态：待开发
- 目标：让 `/admin` 工作台展示真实业务统计。
- 建议范围：
  - `routes/web.php`
  - Dashboard 页面
  - 统计服务
- 功能要求：
  - 展示用户数、角色数、指标数、导入任务数。
  - 首页统计使用缓存。
  - 缓存失效策略有文档说明。
- 验收标准：
  - 数据与数据库记录一致。
  - 缓存命中后接口或页面加载更快。

### P3-04 Docker 一键启动验收

- 状态：已完成
- 目标：把 Docker 配置从静态校验推进到完整运行验收。
- 建议范围：
  - `docker-compose.yml`
  - `docker/`
  - `scripts/deploy/docker-smoke.sh`
  - `docs/deploy/docker-deploy-runbook.md`
- 功能要求：
  - 完整验证 Nginx、PHP-FPM、MySQL、Redis、Queue、Scheduler。
  - 补容器启动、迁移、Seed、队列消费、日志查看命令。
  - 补 502、504、权限、数据库连接失败排查。
- 验收标准：
  - `docker compose up -d --build` 后可以访问 `/login` 和 `/docs/api`。
  - 队列和调度容器正常运行。
- 完成证据：
  - `docker/php/Dockerfile` 已补 Node/npm 和 `phpredis` 扩展，支持容器内前端构建和 Redis Queue。
  - `docker-compose.yml` 已补健康检查、启动依赖和 `restart: unless-stopped`，`queue:restart` 后 Worker 可自动拉起。
  - `docker/nginx/default.conf` 已使用 Docker DNS 动态解析 `app:9000`，避免 app 重建后 Nginx 指向旧 FastCGI IP。
  - `scripts/deploy/docker-smoke.sh` 已提供一键验收：Compose config、build/up、composer、npm build、key、migrate/seed、queue restart、Kafka topics 和 HTTP 检查。
  - `docs/deploy/docker-deploy-runbook.md` 已补访问路径、日志命令、发布回滚和 502/504/MySQL/Redis/Kafka/权限排障表。
  - `tests/Feature/PhaseFourteenDockerRunbookTest.php` 已覆盖 Compose 配置、smoke 脚本和部署 Runbook。
  - 真实 Docker smoke 已通过，最终 `app`、`nginx`、`mysql`、`redis`、`queue`、`scheduler`、`kafka` 均处于运行状态，`/login`、`/docs/api`、`/api/v1/health` 可访问。

### P3-05 语义搜索和 AI 加分模块

- 状态：暂缓
- 目标：作为后期面试加分模块，补充 Laravel 13 AI SDK、向量搜索或语义检索示例。
- 建议范围：
  - 新增独立模块，避免影响主业务闭环。
  - 新增文档记录选型和边界。
- 功能要求：
  - 指标名称、描述、标签支持语义检索。
  - 支持降级到普通关键词搜索。
- 验收标准：
  - 有可演示入口。
  - 不依赖生产密钥才能运行基础测试。

## 7. 推荐下一轮开发顺序

1. P3-05 语义搜索和 AI 加分模块。

原因：P0 后台真实数据联动、P1-02 静态分析基线、P1-04 Kafka 事件流闭环、P1-03 MQ 队列可靠性、P1-01 Redis 缓存专题、P1-06 Laravel 源码专题、P1-08 MySQL 大数据性能实证、P1-05 运行机制专题、P1-07 PHP 语言底层实验、P2-03 CI、P2-01 OpenAPI、P2-02 测试覆盖增强、P2-04 安全攻防、P3-01 Excel 导入、P3-02 大数据导出异步化和 P3-04 Docker 一键启动验收均已完成。后续补语义搜索/AI 加分模块。每个任务都必须保持 `composer analyse` 通过，并在专题文档中补基础问题、资深追问、生产风险和验收证据。

执行说明：下一轮 agent 领取上述任务时，先读取 `docs/interview/architect-interview-coverage-plan.md` 的“后续 Agent 执行任务卡”，再按本文档更新任务状态。任务没有代码入口、测试或可验证命令时，不得标记为已完成。
