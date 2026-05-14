# 待开发任务清单

更新日期：2026-05-14  
适用分支：`13.x`  
执行基线：`docs/development-completion-review.md`  
主计划：`docs/implementation-execution-plan.md`

本文档记录首轮 Phase 1 到 Phase 6 完成后的后续开发任务。后续 Codex agent 可以按优先级领取任务，每个任务完成后必须同步更新本文档状态、相关接口文档和验收记录。

当前优先级调整：

- PHPStan/Larastan/Psalm 已提升为 P1 工程质量基线，后续每个开发任务都要先保证 `composer analyse` 可通过。
- Kafka 使用专题已提升为 P1 下一核心开发项，优先级高于普通 MQ 对比、Redis 扩展和产品增强模块。

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
2. P1-04 Kafka 使用专题实验：下一项高优先级开发任务。
3. P1-03 MQ 与队列可靠性专题：与 Kafka 实现联动补强。
4. P1-01 Redis 缓存专题实验：在 Kafka 主链路完成后继续增强缓存面试场景。

### P1-01 Redis 缓存专题实验

- 状态：待开发
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

- 状态：待开发
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

### P1-04 Kafka 使用专题实验

- 状态：待开发
- 当前优先级：P1 高，P1-02 静态分析基线完成后的下一项核心开发任务。
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

### P1-05 多进程、Worker 与 Octane 专题

- 状态：待开发
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

### P1-06 Laravel 源码专题文档

- 状态：待开发
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

## 5. P2 工程质量、接口文档和 CI/CD

### P2-01 OpenAPI 中文化和示例补全

- 状态：待开发
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

### P2-02 测试覆盖增强

- 状态：待开发
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

### P2-03 GitHub Actions CI

- 状态：待开发
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

## 6. P3 产品增强和加分模块

### P3-01 Excel 导入

- 状态：待开发
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

### P3-02 大数据导出异步化

- 状态：待开发
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

- 状态：待开发
- 目标：把 Docker 配置从静态校验推进到完整运行验收。
- 建议范围：
  - `docker-compose.yml`
  - `docker/`
  - `docs/deploy/docker-deploy-runbook.md`
- 功能要求：
  - 完整验证 Nginx、PHP-FPM、MySQL、Redis、Queue、Scheduler。
  - 补容器启动、迁移、Seed、队列消费、日志查看命令。
  - 补 502、504、权限、数据库连接失败排查。
- 验收标准：
  - `docker compose up -d --build` 后可以访问 `/login` 和 `/docs/api`。
  - 队列和调度容器正常运行。

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

1. P1-04 Kafka 使用专题实验。
2. P1-03 MQ 与队列可靠性专题。
3. P2-01 OpenAPI 中文化和示例补全。
4. P1-01 Redis 缓存专题实验。
5. P3-04 Docker 一键启动验收。
6. P3-01 Excel 导入。
7. P3-02 大数据导出异步化。

原因：P0 后台真实数据联动已完成，P1-02 静态分析基线也已完成，后续开发必须保持 `composer analyse` 通过。下一轮应直接推进 Kafka，把它挂在导入、审计或指标变更的真实业务事件上实现，而不是只做孤立 Demo；P1-03 的 MQ 可靠性专题应围绕 Kafka 和现有 Redis Queue 做对比补强。
