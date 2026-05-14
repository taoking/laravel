# 架构师面试覆盖度评估与补齐计划

更新日期：2026-05-15
评估角色：资深架构师 / PHP 技术面试官
适用分支：`13.x`
执行基线：

- `docs/learning-index.md`
- `docs/development-completion-review.md`
- `docs/pending-development-tasks.md`
- `docs/queue/kafka-practice.md`
- `docs/testing-ci/static-analysis.md`

本文用于把当前项目从“功能完整的学习项目”推进到“能承受资深面试连续追问的项目”。后续 Codex agent 应以本文作为面试深度补齐计划，并在每个任务完成后同步更新 `docs/pending-development-tasks.md`、相关专题文档和 `docs/development-log.md`。

## 0. 本次复审证据

本次复审不是只看计划文档，而是抽查了当前项目的真实入口：

| 检查项 | 证据 | 面试价值 |
| --- | --- | --- |
| 路由规模 | `php artisan route:list --except-vendor` 显示 43 条项目路由 | 能说明后台、API、文档、登录和业务接口已经形成可演示闭环 |
| Kafka 命令 | `php artisan list kafka --raw` 显示 5 个 Kafka 命令 | 能说明 Kafka 不只是文档概念，已有生产、消费、lag、topic、死信重放入口 |
| 后台页面 | `resources/js/Pages/*` 覆盖登录、工作台、用户、角色、菜单、指标、导入、审计 | 能演示企业后台主流程和中文多语言界面 |
| API 主线 | `routes/api.php` 覆盖健康检查、鉴权、权限、指标、导入导出、签名、审计 | 能串起 Auth、RBAC、业务 API、安全和审计 |
| 自动化测试 | `tests/Feature/Phase*Test.php` 覆盖 Phase 1/2/3/4/5/7/8/9/10/11 | 能作为回归和面试证据，后续重点是 CI 固化和浏览器端验收 |
| 静态分析 | `composer.json` 已提供 `analyse`、`analyse:phpstan`、`analyse:psalm` | 已具备 P1 工程质量门禁 |
| 专题文档 | `docs/queue/kafka-practice.md`、`docs/testing-ci/static-analysis.md`、`docs/deploy/docker-deploy-runbook.md` | 已具备专题说明，但部分主题还缺可运行实验和失败案例 |

面试官结论：

- 当前项目已经能支撑“Laravel 企业后台 + PHP 工程化 + 队列/Kafka + 权限审计”的主线表达。
- 当前项目还不能完全支撑“资深架构师连续追问 30 到 60 分钟”的深度，因为部分高频专题只有设计说明或基础代码，没有故障复现、对比实验、性能证据和源码绑定。
- 后续开发不应优先堆产品功能，应优先补齐可靠性、缓存、源码、PHP 底层、MySQL 性能、运行机制、CI/CD 和生产排障证据。

## 1. 总体结论

当前项目已经具备资深 PHP/Laravel 面试的主线：

- Laravel 13 + PHP 8.4 项目骨架。
- Inertia 管理后台和中文多语言。
- Auth、RBAC、用户/角色/菜单权限。
- 指标库、维度、筛选、分页、排序白名单。
- CSV 导入、Redis Queue、Job 幂等、失败记录。
- Kafka 事件流最小闭环。
- 审计日志、接口签名、反重放、限流、上传校验。
- Docker、Nginx、PHP-FPM、Queue Worker、Scheduler 文档。
- PHPStan/Larastan/Psalm 静态分析基线。

但从架构师面试看，当前还存在一个关键问题：部分专题“有入口、有说明”，但还没有足够多的可运行实验和证据来承受 3 到 5 层追问。下一阶段的重点不是继续扩普通产品功能，而是补强可靠性、缓存、源码、性能、运行机制和工程化证据。

建议把面试准备标准从“能讲功能”提升到“能讲事故、边界和取舍”：

| 层级 | 面试官看什么 | 当前状态 | 后续补齐方向 |
| --- | --- | --- | --- |
| L1 用法层 | Laravel API 会不会用，功能是否跑通 | 已基本达标 | 保持接口文档和测试同步 |
| L2 原理层 | 为什么这样设计，底层机制是什么 | 部分达标 | Laravel 源码、PHP 底层、MySQL/Redis 原理要绑定项目代码 |
| L3 生产层 | 失败怎么办，如何观测，如何恢复，如何压测 | 明显不足 | 补故障复现、补偿命令、压测数据、日志样例和排障 Runbook |
| L4 架构层 | 方案边界、选型取舍、演进路线 | 初步具备 | 补 Outbox、MQ 选型、缓存一致性、模块边界和容量评估 |

## 2. 知识覆盖评分

| 维度 | 当前评分 | 证据 | 主要风险 |
| --- | ---: | --- | --- |
| 项目完整度 | 8/10 | 后台、API、导入、审计、Kafka、Docker 均有入口 | 业务闭环够讲，但部分功能仍是学习版 |
| Laravel 使用广度 | 8/10 | Controller、Request、Resource、Model、Policy、Middleware、Job、Event、Command | 源码级解释还不够代码化 |
| Laravel 源码深度 | 7/10 | 已有 Container、Provider、Facade、Pipeline、Router、Eloquent、Queue Worker 专题 | 后续可继续补源码行级阅读和 Octane 常驻容器案例 |
| PHP 语言底层 | 7/10 | 有 `php:language-lab`、COW/引用/对象/Generator/PHP 8.x 特性实验和专题文档 | 缺扩展到真实导入链路的内存曲线和更多 PHP 8.4 生产案例 |
| PHP 运行机制 | 7/10 | 有 FPM/Worker/Octane 文档和 runtime 实验命令 | 缺真实 FPM 压测、Octane 实装和线上内存曲线 |
| MySQL 深度 | 7/10 | 有指标查询、索引、造数命令、Explain 命令和 seek pagination 示例 | 缺真实百万级压测结果、慢 SQL 日志样例和事务锁复现实验 |
| Redis 深度 | 7/10 | 有空值缓存、随机 TTL、token lock、热点 ZSet、Lua 限流实验 | 缺 Redis Cluster、Sentinel、真实大 Key/热 Key 监控和线上指标 |
| Queue/MQ 深度 | 8/10 | Redis Queue 可靠性 + Kafka 事件流已完成闭环 | Outbox Pattern 仍是文档级，RabbitMQ 暂未落地代码 |
| 安全能力 | 7/10 | 签名、反重放、越权、上传校验、审计日志 | SSRF、XSS、CSRF、反序列化、敏感数据脱敏缺专题实验 |
| 架构设计 | 6/10 | Domains、Service、Query Object、Event-Driven 已出现 | DTO、Value Object、Repository 取舍、Outbox、模块边界还需成文 |
| 性能优化 | 6/10 | 缓存、Explain、wrk 脚本、Runbook | 缺基准数据、前后对比、容量估算和 APM 式定位流程 |
| 测试与质量 | 8/10 | Feature Test、Pint、PHPStan、Psalm、GitHub Actions CI | 缺浏览器自动化和覆盖率策略 |
| 部署运维 | 6/10 | Docker Compose、Nginx、FPM、Supervisor | 缺一键启动完整验收、日志样例、502/504 实战复盘 |

## 3. 面试追问地图

### 3.1 Laravel 源码追问

基础问题：

- 一个请求从 `public/index.php` 到 Controller 经过哪些步骤？
- `register()` 和 `boot()` 的区别是什么？
- Facade 是静态方法吗？

资深追问：

- Controller 方法参数里的 `MetricQuery` 是谁解析出来的？
- Service Container 解析接口和具体类失败时如何排查？
- Middleware Pipeline 里异常抛出后响应如何返回？
- Route Model Binding 与 Policy 执行顺序是什么？
- Eloquent `with()`、`load()`、`loadMissing()` 在查询时机上有什么差异？

当前缺口：

- 已完成项目入口绑定源码专题；后续缺口是更细的源码行级阅读、Octane 常驻容器案例和复杂包发现机制。

### 3.2 PHP 底层追问

基础问题：

- PHP 数组为什么既能当 list 又能当 map？
- 写时复制什么时候发生？
- 引用和对象赋值有什么区别？
- `empty($arr['x'])` 为什么不报错？

资深追问：

- 什么时候 COW 会被打破？
- 大数组循环传参为什么可能造成内存抖动？
- Generator 如何降低导入大文件内存？
- `readonly`、Enum、Attribute 在项目中分别解决什么问题？
- `#[\Override]` 在静态分析中解决了什么问题？

当前状态：

- 已新增 `php:language-lab` 和 `docs/php-language/runtime-labs.md`，可用真实输出演示弱类型、COW、引用、对象赋值、Generator、Enum、Attribute、Readonly、Closure 和 `#[\Override]`。
- 后续缺口是把 Generator 内存曲线进一步绑定到真实 CSV 导入和百万行文件压测。

### 3.3 MySQL 追问

基础问题：

- 当前指标查询用了哪些索引？
- 为什么排序字段要白名单？
- N+1 怎么发现和处理？

资深追问：

- 千万级分页如何优化？
- `where function(column)` 为什么可能导致索引失效？
- 覆盖索引、回表、索引下推在当前 SQL 里如何体现？
- MVCC 和间隙锁如何影响导入/更新指标值？
- 死锁如何复现和排查？

当前缺口：

- 已补批量造数、Explain 命令和 seek pagination 示例；后续缺口是百万级真实压测结果、慢 SQL 日志样例、死锁和事务锁复现。

### 3.4 Redis 追问

基础问题：

- Laravel Cache 和直接 Redis 操作有什么区别？
- 缓存穿透、击穿、雪崩分别怎么处理？
- 分布式锁怎么避免误删？

资深追问：

- 如何发现大 Key 和热点 Key？
- 热点指标排行榜为什么适合 ZSet？
- Lua 限流相比普通 Redis 命令有什么优势？
- 缓存和数据库一致性如何取舍？
- 权限缓存更新时如何避免脏读或短暂越权？

当前缺口：

- 已补穿透、击穿、雪崩、锁 token、Lua 限流和热点 ZSet 的代码实验；后续缺口转为 Redis Cluster、Sentinel、真实大 Key/热 Key 监控和线上指标采集。

### 3.5 Queue / MQ / Kafka 追问

基础问题：

- Job 为什么必须幂等？
- Worker 发布后为什么要 `queue:restart`？
- Redis Queue 和 Kafka 的边界是什么？

资深追问：

- Redis Queue 任务执行成功但删除失败怎么办？
- Job 执行一半失败如何补偿？
- Kafka 消费成功但 offset 提交失败如何处理？
- RabbitMQ、Kafka、Redis Queue 如何选型？
- Outbox Pattern 如何解决数据库事务和消息发送一致性？

当前缺口：

- Kafka 和 Redis Queue 可靠性已形成闭环；后续缺口是 Outbox Pattern 代码化和 RabbitMQ 真实连接实践。

### 3.6 安全追问

基础问题：

- CSRF、XSS、SQL 注入在 Laravel 里如何防？
- 接口签名和反重放怎么设计？
- 越权如何测试？

资深追问：

- SSRF 在文件导入、远程数据源、Webhook 中如何出现？
- 文件上传只校验扩展名为什么不够？
- 审计日志如何避免记录敏感字段？
- JWT、Session、Sanctum、Passport 如何选型？
- 敏感接口二次确认如何设计？

当前缺口：

- 缺 SSRF、XSS、脱敏、敏感操作二次确认和安全测试专题。

### 3.7 部署与运维追问

基础问题：

- Nginx 502/504 怎么排查？
- PHP-FPM 进程数怎么估算？
- Supervisor 如何管理 Worker？

资深追问：

- Worker 内存泄漏如何发现？
- Docker 容器启动顺序和健康检查怎么做？
- 发布过程中队列任务如何平滑处理？
- OPcache 开启后为什么代码可能不立即生效？
- 多机 Scheduler 如何避免重复执行？

当前缺口：

- 缺一键 Docker 启动实证、健康检查、日志样例、FPM/Worker 调优实验。

## 4. 补齐任务计划

### AIP-01 MQ 与队列可靠性专题

- 优先级：P1
- 状态：已完成
- 对应待开发项：P1-03
- 目标：把 Redis Queue、Kafka、RabbitMQ 对比和任务可靠性讲深。
- 代码交付：
  - 新增导入任务补偿命令，例如 `imports:compensate`。
  - 为 `ProcessMetricImportJob` 补充更明确的业务幂等说明和失败分类。
  - 增加 failed job、重复执行、补偿执行的 Feature Test。
  - 可选：新增 Outbox 表设计文档或轻量实现。
- 文档交付：
  - 更新 `docs/queue/import-export-worker.md`。
  - 新增或更新 MQ 选型对比：Redis Queue、RabbitMQ、Kafka。
- 验收：
  - 测试覆盖失败、重试、重复执行、补偿。
  - 文档能回答消息丢失、重复消费、顺序性、死信、补偿和选型。
- 完成证据：
  - `ProcessMetricImportJob` 已补充终态幂等、尝试次数和失败分类。
  - `php artisan imports:compensate` 已支持 dry-run、按 ID、按状态、按失败时间窗口和清理失败行补偿。
  - `tests/Feature/PhaseFourImportQueueTest.php` 已覆盖失败、重复执行、补偿和 dry-run。
  - `docs/queue/import-export-worker.md` 已补 Redis Queue、RabbitMQ、Kafka 对比和追问。

### AIP-02 Redis 深度实验

- 优先级：P1
- 状态：已完成
- 对应待开发项：P1-01
- 目标：补齐缓存三大问题、分布式锁、Lua、热点 Key 和大 Key。
- 代码交付：
  - 指标详情空值缓存防穿透。
  - 热点指标缓存加随机 TTL 防雪崩。
  - 单指标重建缓存使用锁防击穿。
  - 导入任务或统计任务使用 token lock，避免误删。
  - Lua 限流示例，可用 Artisan 命令或 Middleware 演示。
- 文档交付：
  - 新增 `docs/redis/cache-reliability.md`。
- 验收：
  - 测试覆盖命中、空缓存、锁释放、Lua 限流。
  - 文档能解释 Laravel Cache 与 Redis 原生命令边界。
- 完成证据：
  - `MetricCacheService` 已覆盖空值缓存、随机 TTL、重建锁和 token lock。
  - `HotMetricService` 已补热点 ZSet 随机 TTL 和 Cache fallback。
  - `RedisRateLimiterService` 与 `redis:cache-lab` 已提供 Lua 限流实验入口。
  - `tests/Feature/PhaseEightRedisCacheReliabilityTest.php` 已覆盖 Redis 缓存可靠性边界。
  - `docs/redis/cache-reliability.md` 已记录缓存三大问题、锁、Lua、大 Key、热 Key 和一致性。

### AIP-03 Laravel 源码追问专题

- 优先级：P1
- 状态：已完成
- 对应待开发项：P1-06
- 目标：把框架源码解释绑定到项目代码，不只写概念。
- 文档交付：
  - `docs/laravel-core/container.md`
  - `docs/laravel-core/service-provider.md`
  - `docs/laravel-core/facade.md`
  - `docs/laravel-core/middleware-pipeline.md`
  - `docs/laravel-core/router-model-binding.md`
  - `docs/laravel-core/eloquent-query.md`
  - `docs/laravel-core/queue-worker.md`
- 验收：
  - 每篇至少绑定一个项目入口、一个源码类、三道追问。
  - 能从 `MetricController@index` 讲到容器、路由、Middleware、Query Object 和 Resource。
- 完成证据：
  - 已新增 `docs/laravel-core/container.md`、`service-provider.md`、`facade.md`、`middleware-pipeline.md`、`router-model-binding.md`、`eloquent-query.md`、`queue-worker.md`。
  - 每篇均包含项目入口、Laravel 源码类、执行链、生产风险、基础问题和资深追问。

### AIP-04 PHP 语言底层代码示例

- 优先级：P1
- 状态：已完成
- 目标：补齐 PHP 语言底层的可运行证据。
- 代码交付：
  - 新增 `app/Console/Commands/PhpLanguageLabCommand.php` 或 `tests/Unit/PhpLanguageFeatureTest.php`。
  - 覆盖 COW、引用、对象赋值、Generator、Enum、Attribute、Readonly、Closure。
- 文档交付：
  - 新增 `docs/php-language/runtime-labs.md`。
- 验收：
  - 命令或测试输出能说明内存变化、引用行为和 Generator 优势。
  - 文档能回答 PHP 7.4 到 8.4 的关键差异。
- 完成证据：
  - 已新增 `app/Console/Commands/PhpLanguageLabCommand.php`。
  - 已新增 `docs/php-language/runtime-labs.md`。
  - 已新增 `tests/Feature/PhaseElevenPhpLanguageLabTest.php`。

### AIP-05 MySQL 大数据性能实证

- 优先级：P1
- 状态：已完成
- 目标：让指标查询优化从说明变成证据。
- 代码交付：
  - 新增大数据 Seeder 或 Artisan 命令生成 10 万到 100 万指标值。
  - 新增 Explain 捕获命令或文档化 SQL。
  - 增加游标分页、ID seek pagination 或覆盖索引示例。
- 文档交付：
  - 更新 `docs/database/metric-query-explain.md`。
  - 新增 `docs/database/large-pagination.md`。
- 验收：
  - 有 Explain 前后对比。
  - 有慢 SQL 或压测输出样例。
  - 能回答索引失效、回表、覆盖索引、分页优化。
- 完成证据：
  - 已新增 `metrics:seed-large-dataset`、`metrics:explain-query` 和 `metrics:seek-page`。
  - 已更新 `docs/database/metric-query-explain.md` 并新增 `docs/database/large-pagination.md`。
  - 已新增 `tests/Feature/PhaseNineDatabasePerformanceTest.php`。

### AIP-06 PHP-FPM、Worker、Octane 与多进程

- 优先级：P1
- 状态：已完成
- 对应待开发项：P1-05
- 目标：补齐 PHP 运行机制和常驻进程追问。
- 代码交付：
  - Artisan 长任务示例。
  - Worker 内存增长模拟命令。
  - Queue restart / graceful shutdown 文档化命令。
- 文档交付：
  - 新增 `docs/runtime/php-fpm-worker-octane.md`。
- 验收：
  - 能解释 FPM、CLI、Queue Worker、Scheduler、Octane 生命周期差异。
  - 能回答 Worker 为什么要 restart、内存泄漏如何发现。
- 完成证据：
  - 已新增 `runtime:worker-lab memory-growth` 和 `runtime:worker-lab lifecycle`。
  - 已新增 `docs/runtime/php-fpm-worker-octane.md`。
  - 已新增 `tests/Feature/PhaseTenRuntimeProcessTest.php`。

### AIP-07 安全攻防增强

- 优先级：P2
- 目标：把安全从“防护说明”推进到“攻击样例 + 测试”。
- 代码交付：
  - SSRF 防护示例。
  - XSS 输出转义与富文本白名单示例。
  - 敏感字段脱敏审计。
  - 敏感操作二次确认或签名增强。
- 文档交付：
  - 更新 `docs/security/security-audit.md`。
  - 新增 `docs/security/web-attack-labs.md`。
- 验收：
  - Feature Test 覆盖越权、签名、上传、SSRF/XSS 基础边界。

### AIP-08 CI/CD 与质量门禁

- 优先级：P2
- 状态：已完成
- 对应待开发项：P2-03
- 目标：把本地质量门禁搬到 CI。
- 代码交付：
  - `.github/workflows/ci.yml`
- CI 步骤：
  - `composer validate --strict`
  - `composer install`
  - `composer analyse`
  - `./vendor/bin/pint --test`
  - `php artisan test`
  - `npm ci`
  - `npm run build`
  - `docker compose config`
- 文档交付：
  - 新增 `docs/testing-ci/github-actions.md`。
- 验收：
  - Push 或 PR 自动运行。
- 完成证据：
  - 已新增 `.github/workflows/ci.yml`。
  - 已新增 `docs/testing-ci/github-actions.md`。
  - 已调整 `.github/workflows/tests.yml` 到 PHP 8.4 基线。
  - CI 失败能阻止合并或在文档中标明处理方式。

### AIP-09 OpenAPI 中文化和接口示例

- 优先级：P2
- 状态：已完成
- 对应待开发项：P2-01
- 目标：接口文档达到调试和面试演示标准。
- 交付：
  - 所有接口中文 summary/description。
  - 请求示例、成功响应示例、401/403/422 示例。
  - 签名、限流、鉴权说明。
- 验收：
  - `/docs/api` 可直接按中文说明调试接口。
  - YAML 与 `php artisan route:list --except-vendor` 对齐。
- 完成证据：
  - `public/docs/openapi.yaml` 已中文化并补请求/错误示例。
  - 已新增 `tests/Feature/PhaseTwelveOpenApiContractTest.php`。

### AIP-10 Docker 一键运行与生产排障

- 优先级：P2
- 对应待开发项：P3-04，可提升为 P2
- 目标：把 Docker 从配置可用推进到完整运行证据。
- 交付：
  - 完整 `docker compose up -d --build` 验收记录。
  - 容器健康检查说明。
  - Nginx、PHP-FPM、MySQL、Redis、Queue、Scheduler、Kafka 日志查看命令。
  - 502/504、权限、数据库连接失败、队列不消费排障表。
- 验收：
  - 容器启动后可访问 `/login`、`/docs/api`、`/api/v1/health`。
  - Queue、Scheduler、Kafka 命令可执行。

## 5. 推荐执行顺序

| 顺序 | 任务 | 原因 |
| ---: | --- | --- |
| 1 | AIP-07 安全攻防增强 | 强化安全专题深度 |
| 2 | AIP-10 Docker 一键运行与生产排障 | 补齐生产部署证据 |
| 3 | P2-02 Excel 导入解析 | 补齐真实企业导入场景 |
| 4 | P3-02 大数据导出异步化 | 补齐大文件导出与下载鉴权 |
| 5 | P3-01 Excel 导入 | 补齐非 CSV 文件导入能力 |

## 6. 后续 Agent 执行任务卡

后续 Codex agent 领取任务时，不要只按标题开发，必须按任务卡补齐代码、测试、文档和面试追问。每个任务完成后都要更新：

- `docs/pending-development-tasks.md`
- 本文档对应任务状态或补充记录
- 对应专题文档
- `docs/development-log.md`
- 如新增 API，更新 `public/docs/openapi.yaml`
- 如新增页面，更新 `docs/learning-index.md`

### 6.1 AIP-01 / P1-03：MQ 与队列可靠性

执行目标：把导入队列从“能异步执行”提升到“能解释可靠性、失败恢复、重复消费和补偿”。

代码路径：

- `app/Jobs/ProcessMetricImportJob.php`
- `app/Domains/Imports/Models/ImportTask.php`
- `app/Http/Controllers/Api/V1/Imports/ImportTaskController.php`
- 新增 `app/Console/Commands/ImportCompensateCommand.php`
- `database/migrations/*import_tasks*`
- `tests/Feature/PhaseFourImportQueueTest.php` 或新增 `PhaseEightQueueReliabilityTest.php`

实施路径：

1. 为导入任务增加 `attempts`、`failure_type`、`last_failed_at`、`compensated_at`、`compensation_reason` 等字段。
2. 明确终态幂等：`completed`、`completed_with_errors` 重复执行时直接跳过。
3. 为缺文件、格式错误、业务行失败、未知异常做失败分类。
4. 新增 `imports:compensate` 命令，支持按任务 ID、状态、失败时间窗口、dry-run 和清理失败行执行补偿。
5. 在重试接口和补偿命令中说明哪些字段重置、哪些字段保留。
6. 文档补充 Redis Queue、RabbitMQ、Kafka 的可靠性差异、死信、顺序性、重复消费和选型。

验收标准：

- 测试覆盖缺文件失败、重试、重复执行跳过、补偿命令成功执行。
- 运行 `php artisan imports:compensate --dry-run` 有可解释输出。
- `docs/queue/import-export-worker.md` 能回答“任务执行成功但 ack/delete 失败怎么办”“Job 执行一半失败怎么办”“为什么 Job 要幂等”。

### 6.2 AIP-02 / P1-01：Redis 深度实验

执行目标：把 Redis 从普通缓存使用提升到缓存可靠性和分布式并发控制专题。

代码路径：

- `app/Domains/Metrics/Services/HotMetricService.php`
- `app/Http/Controllers/Api/V1/Metrics/MetricController.php`
- 可新增 `app/Domains/Metrics/Services/MetricCacheService.php`
- 可新增 `app/Console/Commands/RedisCacheLabCommand.php`
- `tests/Feature/*Redis*Test.php`

实施路径：

1. 指标详情增加空值缓存，演示缓存穿透治理。
2. 热点指标缓存增加随机 TTL，演示雪崩治理。
3. 单指标缓存重建使用带 token 的分布式锁，演示击穿治理和避免误删。
4. 热门指标排行榜使用 ZSet 思路，测试环境可降级为 Cache 适配。
5. 增加 Lua 限流示例或命令，说明原子性。
6. 新增 `docs/redis/cache-reliability.md`。

验收标准：

- 测试覆盖命中、空缓存、随机 TTL、锁释放、重复锁释放保护和限流。
- 文档能回答 Laravel Cache 与 Redis 原生命令边界、大 Key、热 Key、缓存一致性和分布式锁误删。

### 6.3 AIP-03 / P1-06：Laravel 源码追问专题

执行目标：把“会用 Laravel”提升到“能从项目入口讲到框架源码机制”。

文档路径：

- `docs/laravel-core/container.md`
- `docs/laravel-core/service-provider.md`
- `docs/laravel-core/facade.md`
- `docs/laravel-core/middleware-pipeline.md`
- `docs/laravel-core/router-model-binding.md`
- `docs/laravel-core/eloquent-query.md`
- `docs/laravel-core/queue-worker.md`

实施路径：

1. 以 `MetricController@index` 作为 HTTP 请求主线，串起路由、Middleware、Controller、Request、Query Object、Resource。
2. 以 `EnsureUserHasPermission` 说明 Middleware Pipeline 和异常返回。
3. 以 `PermissionService`、`KafkaProducer` 说明 Service Container 解析和依赖注入。
4. 以 `ProcessMetricImportJob` 说明 Queue Worker 生命周期。
5. 每篇文档都写“项目入口、Laravel 源码类、关键机制、追问、生产风险”。

验收标准：

- 每篇至少 1 个项目入口、1 个源码类、5 个追问。
- 能完整回答“Facade 是静态方法吗”“Controller 参数是谁注入的”“Middleware 洋葱模型异常如何返回”。

### 6.4 AIP-04 / P1-07：PHP 语言底层实验

- 状态：已完成

执行目标：用可运行命令或测试证明 PHP 语言机制，不停留在八股。

代码路径：

- `app/Console/Commands/PhpLanguageLabCommand.php`
- `tests/Feature/PhaseElevenPhpLanguageLabTest.php`
- `docs/php-language/runtime-labs.md`

实施路径：

1. 演示数组 copy-on-write 和引用打破 COW。
2. 演示对象赋值、clone 和引用赋值差异。
3. 演示 Generator 读取大文件相对数组加载的内存优势。
4. 演示 Enum、Attribute、Readonly、Closure、Arrow Function 的项目适用场景。
5. 文档补 PHP 7.4 到 PHP 8.4 的面试差异表。

验收标准：

- 命令或测试能输出内存变化、引用行为和 Generator 行为。
- 文档能回答“PHP 数组为什么既能 list 又能 map”“COW 何时发生”“Generator 为什么省内存”。

完成证据：

- `php artisan php:language-lab all --rows=1000`
- `php artisan test --filter=PhaseElevenPhpLanguageLabTest`
- `docs/php-language/runtime-labs.md`

### 6.5 AIP-05 / P1-08：MySQL 大数据性能实证

执行目标：让指标查询优化有 Explain、慢 SQL 和压测证据。

代码路径：

- `app/Domains/Metrics/Queries/MetricQuery.php`
- 可新增 `app/Console/Commands/SeedMetricDatasetCommand.php`
- 可新增 `app/Console/Commands/ExplainMetricQueryCommand.php`
- `docs/database/metric-query-explain.md`
- 新增 `docs/database/large-pagination.md`

实施路径：

1. 新增 10 万到 100 万指标值造数命令，支持 dry-run 和分批写入。
2. 捕获典型查询 Explain，记录索引命中、回表、Using filesort 等信息。
3. 增加普通 offset 分页与 seek pagination 对比。
4. 给出索引调整前后 SQL、Explain 和耗时样例。
5. 补事务、死锁、MVCC、间隙锁的项目化追问。

验收标准：

- 有可复现造数命令和 Explain 输出。
- 文档能回答“千万级分页如何做”“where function(column) 为什么索引失效”“覆盖索引和回表如何判断”。

### 6.6 AIP-06 / P1-05：PHP-FPM、Worker、Octane 与多进程

执行目标：补齐 PHP 运行机制、长进程和生产排障能力。

代码路径：

- 可新增 `app/Console/Commands/RuntimeWorkerLabCommand.php`
- `docs/runtime/php-fpm-worker-octane.md`
- `docs/deploy/docker-deploy-runbook.md`

实施路径：

1. 写清 HTTP/FPM、CLI、Queue Worker、Scheduler、Octane 生命周期差异。
2. 增加长进程内存增长模拟命令。
3. 增加 `queue:restart`、Supervisor graceful stop、部署平滑处理说明。
4. 补 FPM `pm` 模式、进程数估算、OPcache 生效机制。
5. 补 502/504、内存泄漏、Worker 旧代码、Scheduler 多机重复执行排障表。

验收标准：

- 有命令或文档样例能解释 Worker 常驻内存和发布后重启。
- 文档能回答“为什么 PHP-FPM 不适合保存大量状态”“Octane 和 FPM 区别是什么”。

### 6.7 AIP-08 / P2-03：CI/CD 与质量门禁

- 状态：已完成

执行目标：把本地质量检查固化为 CI。

代码路径：

- 新增 `.github/workflows/ci.yml`
- 新增 `docs/testing-ci/github-actions.md`

实施路径：

1. CI 执行 `composer validate --strict`、`composer install`、`composer analyse`。
2. 执行 `php artisan test`、`./vendor/bin/pint --test`。
3. 执行 `npm ci`、`npm run build`。
4. 执行 `docker compose config`。
5. 文档说明失败处理策略和本地复现命令。

验收标准：

- GitHub Actions YAML 可被 `act` 或 GitHub PR 运行。
- CI 失败项能映射到本地修复命令。

完成证据：

- `.github/workflows/ci.yml`
- `.github/workflows/tests.yml`
- `docs/testing-ci/github-actions.md`

### 6.8 AIP-09 / P2-01：OpenAPI 中文化和接口示例

- 状态：已完成

执行目标：让接口文档达到面试演示和后续协作可直接调试的标准。

代码路径：

- `public/docs/openapi.yaml`
- `resources/views/docs/api.blade.php`
- `routes/api.php`
- 涉及 API 的 Feature Test

实施路径：

1. 对所有接口补中文 `summary`、`description`、标签说明。
2. 为登录态接口补 401、403、422 响应示例。
3. 为导入、导出、签名接口补请求示例和错误示例。
4. 为签名、限流、CSRF、Session 鉴权补统一说明。
5. 对照 `php artisan route:list --except-vendor`，确认 YAML 与实际路由一致。

验收标准：

- `/docs/api` 能按中文说明理解主要接口。
- OpenAPI 覆盖当前 43 条项目路由中的 API 路由。
- 文档能回答“接口契约如何维护”“鉴权失败和权限失败如何区分”。

完成证据：

- `public/docs/openapi.yaml`
- `tests/Feature/PhaseTwelveOpenApiContractTest.php`
- `php artisan test --filter=PhaseTwelveOpenApiContractTest`

### 6.9 AIP-07 / P2-04：安全攻防增强

执行目标：把安全能力从常规防护推进到攻击样例、测试和审计证据。

代码路径：

- `app/Http/Middleware/VerifyApiSignature.php`
- `app/Http/Middleware/RecordOperationLog.php`
- `app/Http/Controllers/Api/V1/Security/*`
- `docs/security/security-audit.md`
- 新增 `docs/security/web-attack-labs.md`
- `tests/Feature/*Security*Test.php`

实施路径：

1. 增加 SSRF 防护示例，说明远程数据源、Webhook、文件导入的风险点。
2. 增加 XSS 输出转义和富文本白名单说明。
3. 增加审计日志敏感字段脱敏规则。
4. 增加敏感操作二次确认或签名增强说明。
5. 补越权、上传、签名、反重放、SSRF/XSS 的测试或演示命令。

验收标准：

- Feature Test 覆盖越权、签名、上传、SSRF/XSS 基础边界。
- 文档能回答“只校验文件扩展名为什么不够”“审计日志如何避免泄露敏感数据”“反重放如何设计过期窗口”。

### 6.10 AIP-10 / P3-04：Docker 一键运行与生产排障

执行目标：让 Docker 和部署文档从“配置存在”提升到“可一键验收、可排障复盘”。

代码路径：

- `docker-compose.yml`
- `docker/nginx/default.conf`
- `docker/php/opcache.ini`
- `docker/supervisor/worker.conf`
- `docs/deploy/docker-deploy-runbook.md`

实施路径：

1. 补完整 `docker compose up -d --build` 验收流程。
2. 补容器健康检查、启动顺序、依赖检查和日志查看命令。
3. 补 Nginx 502/504、PHP-FPM 超时、MySQL 连接失败、Redis 队列不消费、Kafka 不可达排障表。
4. 补发布、回滚、`queue:restart`、配置缓存、OPcache 刷新流程。
5. 补本地演示路径：`/login`、`/admin`、`/docs/api`、`/api/v1/health`。

验收标准：

- Docker Compose 配置可校验，服务启动流程有明确命令和预期输出。
- 文档能回答“502/504 怎么排查”“队列线上如何常驻”“多机 Scheduler 如何避免重复执行”。

## 7. 面试通过标准

一个专题补齐后，必须同时满足：

- 有项目内真实代码入口。
- 有可运行命令、HTTP 接口、Feature Test 或 Unit Test。
- 有中文专题文档。
- 有生产风险说明。
- 有至少 5 个基础问题和 5 个资深追问。
- 有验收命令和结果记录。
- 已更新 `docs/development-log.md`。
- 已通过 `composer analyse`、`php artisan test`、`./vendor/bin/pint --test`。

如果只是写了概念文档，没有代码入口或可验证命令，不能算完成。

## 8. 下一步建议

下一轮开发建议直接执行 AIP-07，对应 `docs/pending-development-tasks.md` 中的 P2-04。

目标是把安全能力从“已有基础防护”推进到“攻击样例 + 测试 + 生产风险说明”：

- 补 SSRF、XSS、CSRF、SQL 注入、反序列化和敏感字段脱敏说明。
- 补文件上传仅校验扩展名的风险说明。
- 补越权、签名、上传、SSRF/XSS 的测试或演示命令。
