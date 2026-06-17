# 【Redis Queue、Horizon 与失败重试】技术方案设计

> 来源：根据 `docs/live-video-chat/10-mainstream-roadmap.md`、`docs/live-video-chat/laravel-live-video-chat-roadmap.md` 和 `docs/live-video-chat/prompt.md` 整理。

## 1. 模块定位

Redis Queue、Horizon 与失败重试属于 **技术功能模块**。

视频转码、回放生成、通知、清理和统计都需要异步队列，生产还需要监控、重试、超时和优先级。

它涉及的核心组件是：Redis queue、Laravel Horizon、failed_jobs、Supervisor、Job timeout/tries、队列优先级。

## 2. 解决的问题

- 用户层面：用户上传或操作后不用等待耗时任务完成，失败后能看到状态并重试。
- 系统层面：把耗时和不稳定任务从 HTTP 请求剥离，并可监控队列积压。
- 不做的问题：转码放在请求里会超时；失败任务无记录；任务积压时系统没有可观测性。
- 所在位置：这是整个视频直播项目工程化的核心底座。

## 3. 常见方案对比

| 方案 | 核心思路 | 需要组件 | 优点 | 缺点 | 适合阶段 | 是否推荐 |
| --- | --- | --- | --- | --- | --- | --- |
| database queue | 任务存在数据库 jobs 表 | MySQL queue | 安装简单 | 吞吐和监控有限 | 学习起步 | 已有可用 |
| Redis queue | 任务存在 Redis，worker 消费 | Redis | 吞吐更好 | 需 Redis 稳定 | 学习项目 | 推荐 |
| Horizon | Redis queue 可视化监控和管理 | Laravel Horizon | 监控重试方便 | 需要部署守护 | 项目展示版 | 推荐升级 |
| 独立任务平台 | 转码服务独立调度，消息队列解耦 | Kafka/RabbitMQ/自研调度 | 大规模可扩展 | 复杂 | 大型平台 | 当前不做 |

## 4. 推荐学习方案

当前学习项目推荐方案：

```text
方案名称：Redis Queue + 分队列 + failed_jobs + Horizon
核心组件：Redis、Queue Worker、Horizon、Supervisor、failed_jobs
Laravel 职责：派发任务、定义 timeout/tries/backoff、更新业务状态、提供重试入口
中间件职责：Redis 保存任务，Supervisor/Horizon 守护 worker
数据流：HTTP 请求写业务记录 -> dispatch Job -> worker 执行 -> 成功更新状态 -> 失败进 failed_jobs 并告警
适合原因：与当前 Laravel 项目天然契合，能讲清异步化和削峰
不足：复杂转码调度和多机器资源管理仍需专门方案
后续升级：独立转码队列、优先级、自动扩容、队列指标告警
```

## 5. 生产环境方案、容量和架构设计

### 5.1 生产环境常见方案

| 方案 | 典型架构 | 适合规模 | 优点 | 缺点 | 成本 | 维护难度 | 是否推荐 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 小型业务方案 | Laravel + MySQL + Redis，异步任务平台 单机运行 | 学习项目到小型站点 | 实现成本低，便于排查 | 容量和高可用有限 | 低 | 低 | 学习期推荐 |
| 中型业务方案 | Redis queue、Laravel Horizon、failed_jobs、Supervisor、Job timeout/tries、队列优先级，队列和缓存拆分，关键任务异步 | 有稳定用户和内容增长 | 吞吐更好，边界清晰 | 需要监控和运维 | 中 | 中 | 推荐演进 |
| 大型业务方案 | 异步任务平台 服务化，Redis Cluster，多 worker，多节点 WebSocket/媒体/存储 | 高并发平台 | 可横向扩展 | 架构复杂 | 高 | 高 | 只做设计理解 |
| 云服务方案 | 云对象存储、云 CDN、云直播、云监控或云 IM 等托管能力 | 快速上线或团队较小 | 稳定省运维 | 费用和厂商绑定 | 中到高 | 低到中 | 生产可选 |
| 自建方案 | 自建媒体服务器、对象存储、监控、队列和边缘节点 | 有基础设施团队 | 可控性强 | 建设和维护成本高 | 高 | 高 | 当前不建议实现 |

### 5.2 生产环境容量估算

需要提前估算：

- 每日转码任务数、单任务耗时、worker 数量、峰值积压、Redis 内存、失败任务保留期

估算方式：

```text
先估算峰值用户行为，再换算为数据库写入、Redis key、队列任务、文件容量或广播扇出。
例如一个高峰动作每秒 1000 次，如果每次都同步写 MySQL 热点行，很快会遇到锁竞争；
学习版可以直接写库，项目展示版应增加 Redis 计数、队列聚合或缓存快照。
```

### 5.3 关键设计问题

- Laravel 负责业务控制、权限、状态和元数据，不直接承担大流量或长耗时处理。
- 中间件负责它擅长的事情：Redis 做状态和削峰，Queue 做异步任务，媒体服务器做直播流，对象存储/CDN 做文件分发。
- 所有状态变化要可追踪，失败要能重试或补偿。
- 对用户可见的数据允许最终一致时，要明确同步周期和兜底校准方式。

### 5.4 典型容量瓶颈和解决方式

| 瓶颈 | 表现 | 原因 | 解决方式 |
| --- | --- | --- | --- |
| 转码任务占满所有 worker | 请求变慢、数据不准或任务积压 | 设计不当或容量增长过快 | 拆分异步任务、增加缓存/索引、扩展 worker 或改用专门中间件 |
| Redis 内存不足 | 请求变慢、数据不准或任务积压 | 设计不当或容量增长过快 | 拆分异步任务、增加缓存/索引、扩展 worker 或改用专门中间件 |
| failed_jobs 无人处理 | 请求变慢、数据不准或任务积压 | 设计不当或容量增长过快 | 拆分异步任务、增加缓存/索引、扩展 worker 或改用专门中间件 |
| 任务重试风暴 | 请求变慢、数据不准或任务积压 | 设计不当或容量增长过快 | 拆分异步任务、增加缓存/索引、扩展 worker 或改用专门中间件 |

### 5.5 学习版到生产版的演进路线

- 本地学习版：Laravel + MySQL + Redis + 本地 storage，先跑通闭环。
- 项目展示版：引入 Redis queue、状态日志、后台管理、基础监控。
- 小型生产版：Nginx、Supervisor、对象存储、CDN、HTTPS、告警。
- 中大型生产版：多节点、独立 worker、Redis Cluster、读写分离、专门媒体/搜索/监控组件。
- 云服务方案：优先使用云点播、云直播、云 CDN、云对象存储降低运维复杂度。

### 5.6 生产环境面试讲解

如果这个模块从 Demo 变成生产系统，我会先拆清 Laravel 和中间件边界；同步请求只做必要校验和状态写入，耗时任务交给队列，高频状态交给 Redis，大文件和媒体流交给对象存储、CDN 或媒体服务器。容量估算重点看 每日转码任务数、单任务耗时、worker 数量、峰值积压、Redis 内存、失败任务保留期。流量上来后，优先处理 转码任务占满所有 worker、Redis 内存不足。

## 6. 系统架构图

```mermaid
graph LR
    browser[浏览器]
    laravel[Laravel应用]
    mysql[MySQL]
    redis[Redis]
    queue[Laravel队列]
    worker[队列Worker]
    module[异步任务平台]

    browser --> laravel
    laravel --> mysql
    laravel --> redis
    laravel --> queue
    queue --> worker
    laravel --> module
    worker --> module
```

```mermaid
sequenceDiagram
    participant U as 用户
    participant B as 浏览器
    participant L as Laravel
    participant R as Redis
    participant Q as Queue
    participant C as 异步任务平台

    U->>B: 发起Redis Queue、Horizon 与失败重试相关操作
    B->>L: 请求接口
    L->>L: 鉴权、校验、状态判断
    L->>R: 读写缓存、限流或临时状态
    L->>Q: 派发异步任务
    Q->>C: 执行耗时或聚合处理
    C-->>L: 更新结果
    L-->>B: 返回页面或 JSON 状态
```

## 7. 核心流程

### 正常流程

1. 用户在页面发起操作。
2. 浏览器请求 Laravel 接口，并携带登录态或必要参数。
3. Laravel 通过 Policy / FormRequest / Service 校验权限和输入。
4. MySQL 写入业务记录、状态或审计日志。
5. Redis 处理限流、缓存、计数、锁或在线状态。
6. Queue 处理耗时任务、通知、聚合、清理或重试。
7. 相关中间件完成媒体、存储、实时通信或监控职责。
8. 页面展示最新状态，必要时通过轮询或 WebSocket 接收结果。

### 异常流程

- 权限不足：返回 403，并记录必要审计。
- Redis 不可用：降级到数据库快照或直接提示稍后重试。
- 队列未启动：业务状态停留在 pending/processing，后台健康检查报警。
- 并发请求：使用唯一索引、Redis lock 或状态机防重复执行。
- 中间件异常：记录错误摘要，避免把底层异常直接暴露给用户。

## 8. Laravel 实现拆分

### 8.1 数据库表

- jobs：database queue 可选
- failed_jobs：失败任务
- video_processing_logs：job_name、video_id、duration、status
- queue_metrics_snapshots：queue、pending、failed、processed

### 8.2 Model

- Video hasMany ProcessingLog
- QueueMetricSnapshot 用于报表

### 8.3 Controller

- AdminQueueController：失败任务列表、重试、队列状态
- VideoRetryController：业务级重新转码

Controller 只负责接收请求、调用授权、组织响应；复杂状态、文件、队列和中间件调用应放到 Service 或 Job。

### 8.4 Service / Support / Action

- QueueDispatchService：统一分配队列名
- JobFailureHandler：失败状态更新
- QueueHealthService：读取队列积压

### 8.5 Job

- ProcessUploadedVideoJob
- GenerateAdaptiveHlsJob
- CleanupVideoFilesJob
- FlushMetricsJob
- SendNotificationJob

Job 需要设置合理的 `tries`、`timeout`、`backoff`，失败后更新业务状态并写入可排查的错误摘要。

### 8.6 Event / Listener

- JobProcessing、JobProcessed、JobFailed、QueueBacklogHigh

### 8.7 WebSocket / Broadcasting

- private-admin.queue 推送队列异常；private-user.{id} 推送视频处理结果

### 8.8 Policy / Middleware

- 只有管理员可查看和重试底层 failed_jobs；用户只能重试自己的视频业务任务

## 9. Docker 组件选择

| 组件 | 镜像示例 | 用途 | 本地学习是否需要 |
| --- | --- | --- | --- |
| MySQL | mysql:8.0 | 业务数据、状态、审计日志 | 需要 |
| Redis | redis:7-alpine | 缓存、限流、队列、在线状态 | 多数模块需要 |
| Redis | redis:7-alpine | 队列 backend | 需要 |
| Supervisor | supervisord | 生产守护 worker/reverb | 生产需要 |
| Horizon | laravel/horizon | 队列监控 | 后续扩展 |

## 10. 数据和状态设计

核心状态：

- job：queued -> processing -> succeeded / failed -> retrying
- video.processing_status 与任务状态同步

状态设计原则：

- 状态迁移必须有明确触发者：用户、管理员、队列任务、媒体服务器回调或定时任务。
- 状态变化失败时要保留错误原因，并允许重试或人工处理。
- 页面展示使用业务状态，不直接暴露内部异常栈。

## 11. Redis / Queue / WebSocket 使用方式

### 11.1 Redis

- queue:{name} 任务队列
- Horizon 运行指标
- 任务去重锁避免重复转码
- 队列积压告警 key

### 11.2 Queue

- 按 transcode、default、notifications、metrics 分队列
- 转码任务 timeout 较长，通知任务短 timeout
- 失败任务保留错误摘要

### 11.3 WebSocket / Reverb

- 任务结果通过 private channel 通知用户或管理员

## 12. 安全风险

- 任务重复执行：Job 幂等和唯一锁
- 队列积压：分队列和 worker 扩容
- 失败无告警：failed 事件记录和通知
- 超时杀死 FFmpeg：合理 timeout 和进程清理

## 13. 性能和扩展

- 学习版：单机 Laravel + MySQL + Redis 可以支撑低并发演示和手动验收。
- 第一类瓶颈：转码任务占满所有 worker。
- 第二类瓶颈：Redis 内存不足。
- 扩展方式：先拆异步任务和缓存，再拆专门中间件，最后考虑多节点和云服务。
- 存储扩展：本地 storage -> MinIO/S3 -> CDN。
- 队列扩展：database queue -> Redis queue -> Horizon -> 多 worker / 多机器。
- 实时扩展：单 Reverb -> 多节点 Reverb + Redis pub/sub 或专门网关。

## 14. 监控和排查

重点指标：

- 队列长度
- 处理耗时
- 失败率
- 重试次数
- worker 存活数

本地排查方式：

- `storage/logs/laravel.log`
- `php artisan queue:failed`
- `docker compose logs`
- 浏览器 Network / Console
- Redis CLI 查看关键 key
- MySQL 查询状态表和日志表

## 15. 分阶段实施路线

- Phase 1：database queue 明确失败状态和重试入口
- Phase 2：切 Redis queue、分队列、Horizon 面板
- Phase 3：多 worker、多机器转码、自动告警和优先级调度

验收标准：

- Phase 1：本地能跑通主流程，有最小权限校验和失败提示。
- Phase 2：状态、队列、Redis、日志和后台入口完整，能作为简历项目讲解。
- Phase 3：能说明生产环境如何扩容、如何监控、如何控制成本和风险。

## 16. 面试讲解角度

### 16.1 这个功能为什么要这样设计？

因为 转码放在请求里会超时；失败任务无记录；任务积压时系统没有可观测性。，所以需要把业务状态、技术组件和异常处理拆清楚。

### 16.2 Laravel 在里面负责什么？

Laravel 负责认证授权、业务状态、数据库元数据、队列派发、事件通知和后台管理。

### 16.3 中间件负责什么？

中间件负责缓存、限流、异步执行、媒体处理、实时通信、文件分发或监控采集，避免 Laravel 承担不适合的工作。

### 16.4 为什么不能全部用 Laravel 直接做？

Laravel 适合业务控制，不适合长时间转码、大文件分发、高频实时连接或大规模计数。全部塞进 Laravel 会导致请求超时、内存压力、连接数瓶颈和维护困难。

### 16.5 如果数据量 / 流量变大，怎么扩展？

先做 Redis 缓存和计数削峰，再把耗时逻辑放进队列；文件和媒体流迁移到对象存储、CDN 或媒体服务器；最后按业务压力拆分 worker、WebSocket、搜索或监控组件。

### 16.6 失败场景怎么处理？

失败要记录状态、错误摘要和可重试入口。队列任务失败进入 failed_jobs，用户看到可理解的状态，管理员能在后台查看和处理。

### 16.7 安全风险怎么处理？

围绕越权、刷接口、路径安全、敏感信息泄露和管理员误操作做防护，关键动作都要走 Policy、RateLimiter、审计日志和输入校验。

### 16.8 这个方案还有哪些不足？

学习版强调可理解和可验收，不覆盖复杂推荐、版权识别、支付结算、跨地域高可用和大型风控系统。生产环境需要按业务规模逐步引入专门服务。

## 17. 总结

队列设计的核心是让耗时任务异步、可重试、可监控，并通过分队列避免转码这种重任务拖垮通知和清理等轻任务。

## 一句话总结

Redis Queue、Horizon 与失败重试的核心设计是：Laravel 管业务和状态，中间件管性能和基础设施，所有高频、耗时、可失败的部分都要异步化、可观测、可重试。
