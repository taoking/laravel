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

## 2. 知识覆盖评分

| 维度 | 当前评分 | 证据 | 主要风险 |
| --- | ---: | --- | --- |
| 项目完整度 | 8/10 | 后台、API、导入、审计、Kafka、Docker 均有入口 | 业务闭环够讲，但部分功能仍是学习版 |
| Laravel 使用广度 | 8/10 | Controller、Request、Resource、Model、Policy、Middleware、Job、Event、Command | 源码级解释还不够代码化 |
| Laravel 源码深度 | 5/10 | 有生命周期和框架学习文档 | Container、Facade、Pipeline、Eloquent、Queue Worker 缺逐类源码阅读笔记 |
| PHP 语言底层 | 4/10 | 知识地图中有规划 | 缺数组、COW、引用、Generator、Attribute、Enum 的可运行示例 |
| PHP 运行机制 | 5/10 | 有 FPM/部署文档 | 缺 FPM 进程估算、Worker 内存泄漏、常驻进程、Octane 对比实验 |
| MySQL 深度 | 6/10 | 有指标查询、索引和 Explain 文档 | 缺大数据量 Seeder、压测结果、慢 SQL、分页优化对比 |
| Redis 深度 | 5/10 | 有缓存、限流、HotMetricService | 缺穿透/击穿/雪崩、大 Key、热点 Key、分布式锁误删、Lua 原子脚本实验 |
| Queue/MQ 深度 | 7/10 | Redis Queue + Kafka 已完成最小闭环 | Redis Queue 可靠性、补偿命令、RabbitMQ 对比仍不足 |
| 安全能力 | 7/10 | 签名、反重放、越权、上传校验、审计日志 | SSRF、XSS、CSRF、反序列化、敏感数据脱敏缺专题实验 |
| 架构设计 | 6/10 | Domains、Service、Query Object、Event-Driven 已出现 | DTO、Value Object、Repository 取舍、Outbox、模块边界还需成文 |
| 性能优化 | 6/10 | 缓存、Explain、wrk 脚本、Runbook | 缺基准数据、前后对比、容量估算和 APM 式定位流程 |
| 测试与质量 | 7/10 | Feature Test、Pint、PHPStan、Psalm | 缺 CI、浏览器自动化、覆盖率策略 |
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

- 需要把源码阅读绑定到项目代码，例如 `MetricController@index`、`EnsureUserHasPermission`、`AppServiceProvider`、`KafkaConsumerService`。

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

当前缺口：

- 缺一组 Artisan 命令或测试，用真实输出演示内存、引用、Generator、Attribute、Enum。

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

- 缺批量造数、Explain 前后对比、慢 SQL 日志样例和分页优化实证。

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

- 缺穿透、击穿、雪崩、锁 token、Lua 限流、大 Key/热点 Key 的代码实验和测试。

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

- Kafka 已有最小闭环，但 Redis Queue 可靠性、补偿命令、Outbox 和 RabbitMQ 对比还要补齐。

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

### AIP-02 Redis 深度实验

- 优先级：P1
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

### AIP-03 Laravel 源码追问专题

- 优先级：P1
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

### AIP-04 PHP 语言底层代码示例

- 优先级：P1
- 目标：补齐 PHP 语言底层的可运行证据。
- 代码交付：
  - 新增 `app/Console/Commands/PhpLanguageLabCommand.php` 或 `tests/Unit/PhpLanguageFeatureTest.php`。
  - 覆盖 COW、引用、对象赋值、Generator、Enum、Attribute、Readonly、Closure。
- 文档交付：
  - 新增 `docs/php-language/runtime-labs.md`。
- 验收：
  - 命令或测试输出能说明内存变化、引用行为和 Generator 优势。
  - 文档能回答 PHP 7.4 到 8.4 的关键差异。

### AIP-05 MySQL 大数据性能实证

- 优先级：P1
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

### AIP-06 PHP-FPM、Worker、Octane 与多进程

- 优先级：P1
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
  - CI 失败能阻止合并或在文档中标明处理方式。

### AIP-09 OpenAPI 中文化和接口示例

- 优先级：P2
- 对应待开发项：P2-01
- 目标：接口文档达到调试和面试演示标准。
- 交付：
  - 所有接口中文 summary/description。
  - 请求示例、成功响应示例、401/403/422 示例。
  - 签名、限流、鉴权说明。
- 验收：
  - `/docs/api` 可直接按中文说明调试接口。
  - YAML 与 `php artisan route:list --except-vendor` 对齐。

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
| 1 | AIP-01 MQ 与队列可靠性专题 | 承接现有 Redis Queue 和 Kafka，是最高频资深追问 |
| 2 | AIP-02 Redis 深度实验 | Redis 是 PHP 后端高频核心能力，当前深度不足 |
| 3 | AIP-03 Laravel 源码追问专题 | 区分会用 Laravel 和理解 Laravel |
| 4 | AIP-05 MySQL 大数据性能实证 | 指标分析平台必须能证明查询优化 |
| 5 | AIP-06 PHP-FPM、Worker、Octane 与多进程 | 补齐 PHP 运行机制和生产排障能力 |
| 6 | AIP-04 PHP 语言底层代码示例 | 让语言基础从八股变成可运行实验 |
| 7 | AIP-08 CI/CD 与质量门禁 | 保护后续开发质量 |
| 8 | AIP-09 OpenAPI 中文化和接口示例 | 提升演示与协作体验 |
| 9 | AIP-07 安全攻防增强 | 强化安全专题深度 |
| 10 | AIP-10 Docker 一键运行与生产排障 | 补齐生产部署证据 |

## 6. 面试通过标准

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

## 7. 下一步建议

下一轮开发建议直接执行 AIP-01，对应 `docs/pending-development-tasks.md` 中的 P1-03。

目标是让当前导入队列具备完整可靠性案例：

- 明确幂等键和状态机。
- 增加补偿命令。
- 覆盖失败、重试、重复执行和补偿测试。
- 文档对比 Redis Queue、RabbitMQ、Kafka。
- 把 Kafka 已完成内容作为事件流案例，把 Redis Queue 作为任务队列案例，两者边界讲清楚。
