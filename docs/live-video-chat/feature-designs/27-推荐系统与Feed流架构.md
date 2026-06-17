# 【推荐系统与Feed流架构】技术方案设计

> 来源：根据 `docs/live-video-chat/11-senior-backend-interview-system-design.md` 的 `0.3 资深后端面试视角下仍需补充的方向`，并参考 `docs/live-video-chat/prompt.md` 的输出规范整理。

## 1. 模块定位

推荐系统与Feed流架构属于 **业务 + 技术混合的平台级模块**。

推荐系统既决定用户看到什么内容，也依赖曝光、点击、播放、完播、互动等数据，是主流视频平台的内容分发核心。

它涉及的核心组件是：Laravel、MySQL、Redis Sorted Set、事件采集、离线特征、召回服务、排序服务、Feed API。

## 2. 解决的问题

- 用户层面：用户打开首页后能看到更符合兴趣的新视频、热门视频、关注内容和相似内容，而不是只能搜索或按时间倒序浏览。
- 系统层面：把内容召回、排序、过滤、去重、分页游标、曝光回传和反馈闭环组织成可扩展链路。
- 如果不做：没有推荐和 Feed，视频平台只能靠搜索、分类和热门榜，内容消费效率和创作者分发能力都不足。
- 在视频 / 直播 / 聊天平台中的位置：位于内容生产之后、用户消费之前，是视频平台增长和留存的核心入口。

## 3. 常见方案对比

| 方案 | 核心思路 | 需要组件 | 优点 | 缺点 | 适合阶段 | 是否推荐 |
| --- | --- | --- | --- | --- | --- | --- |
| 人工运营 Feed | 管理员配置精选内容和排序 | MySQL、后台 | 实现简单，可控 | 个性化弱，人工成本高 | 学习版 / 冷启动 | 推荐起步 |
| 规则推荐 | 按分类、标签、热度、时间、关注关系混合排序 | MySQL、Redis | 可解释，适合项目展示 | 效果有限，容易同质化 | 学习到小型业务 | 推荐 |
| 召回 + 排序两阶段 | 多路召回候选，再用排序分计算 | Redis、特征表、队列 | 接近真实平台 | 需要数据闭环 | 中型业务 | 推荐演进 |
| 机器学习推荐平台 | 特征工程、模型训练、在线推理和 A/B 测试 | Kafka、OLAP、Feature Store、模型服务 | 效果上限高 | 复杂度高，成本高 | 大型平台 | 只做设计理解 |

## 4. 推荐学习方案

当前学习项目推荐方案：

```text
方案名称：规则召回 + Redis 热榜 + 游标分页 Feed
核心组件：videos、tags、follows、video_metrics、Redis Sorted Set、FeedService
Laravel 职责：提供 Feed API、过滤不可见内容、混合召回、游标分页、曝光回传
中间件职责：Redis 保存热榜和候选集合，队列聚合互动指标
数据流：用户请求 Feed -> 多路召回 -> 过滤已看/不可见 -> 排序打分 -> 返回游标 -> 前端曝光回传
适合原因：不用机器学习也能讲清推荐系统核心闭环
不足：个性化粗，冷启动和探索能力有限
后续升级：事件数据管道、用户画像、召回排序分离、A/B 测试
```

## 5. 生产环境方案、容量和架构设计

### 5.1 生产环境常见方案

| 方案 | 典型架构 | 适合规模 | 优点 | 缺点 | 成本 | 维护难度 | 是否推荐 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 小型业务方案 | Laravel + MySQL + Redis，推荐服务 以规则和配置为主 | 学习项目到小型产品 | 实现成本低，易排查 | 能力边界有限 | 低 | 低 | 推荐学习 |
| 中型业务方案 | Laravel、MySQL、Redis Sorted Set、事件采集、离线特征、召回服务、排序服务、Feed API，增加异步任务、缓存、后台和指标 | 稳定增长业务 | 可扩展，可运营 | 需要监控和运维 | 中 | 中 | 推荐演进 |
| 大型业务方案 | 推荐服务 平台化，接入事件流、OLAP、调度、自动化治理 | 高并发平台 | 能力完整，抗风险强 | 复杂度和成本高 | 高 | 高 | 只做设计理解 |
| 云服务方案 | 使用云厂商托管能力或第三方 SaaS | 快速上线或团队较小 | 省运维，稳定 | 厂商绑定和费用不可忽视 | 中到高 | 低到中 | 生产可选 |
| 自建方案 | 自建数据、调度、风控、媒体或分析平台 | 有基础设施团队 | 可控性强 | 建设周期长 | 高 | 高 | 大型团队再考虑 |

### 5.2 生产环境容量估算

需要提前估算：

- DAU、Feed QPS、每次候选数量、曝光事件量、用户已看集合大小、热榜刷新周期

估算方式：

```text
先从业务峰值倒推技术容量：用户量、请求量、事件量、文件量、带宽、队列任务和缓存规模。
再按高峰系数、保留周期、失败重试比例和降级策略估算真实资源。
如果容量估算无法解释成本和瓶颈，这个设计就还停留在 Demo 层面。
```

### 5.3 关键设计问题

- Laravel 负责业务控制、权限、元数据、状态流转和后台管理。
- 高频、耗时、可失败的动作要进入 Redis、Queue、媒体服务、数据平台或 CDN。
- 生产设计必须说明一致性边界：哪些强一致，哪些最终一致，哪些允许降级。
- 资深后端面试中要主动说明容量、成本、稳定性、安全和演进路线。

### 5.4 典型容量瓶颈和解决方式

| 瓶颈 | 表现 | 原因 | 解决方式 |
| --- | --- | --- | --- |
| 在线排序查询过慢 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |
| 曝光事件写入过高 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |
| 已看去重集合过大 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |
| 热门内容过度集中 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |

### 5.5 学习版到生产版的演进路线

- 本地学习版：用 Laravel、MySQL、Redis 和少量配置跑通核心流程。
- 项目展示版：增加状态表、后台入口、Redis 缓存/队列、指标和失败重试。
- 小型生产版：接入对象存储、CDN、Nginx、Supervisor、告警和备份。
- 中大型生产版：按能力拆分专门服务，接入 Kafka、OLAP、调度、风控、HA 和成本治理。
- 云服务方案：优先使用云托管能力降低运维难度，再保留业务抽象避免强绑定。

### 5.6 生产环境面试讲解

如果从 Demo 升级到生产，我会先定义容量指标和故障边界，再把同步链路压到最短；Laravel 只做业务判断和元数据更新，吞吐型任务交给中间件或专门服务。这个模块最容易被追问的是：在线排序查询过慢、曝光事件写入过高、已看去重集合过大，所以需要提前准备降级、监控和成本口径。

## 6. 系统架构图

```mermaid
graph LR
    client[客户端]
    laravel[Laravel业务层]
    mysql[MySQL元数据]
    redis[Redis缓存状态]
    queue[异步队列]
    worker[后台Worker]
    platform[推荐服务]

    client --> laravel
    laravel --> mysql
    laravel --> redis
    laravel --> queue
    queue --> worker
    laravel --> platform
    worker --> platform
```

```mermaid
sequenceDiagram
    participant U as 用户或客户端
    participant L as Laravel
    participant R as Redis
    participant Q as Queue
    participant P as 推荐服务
    participant DB as MySQL

    U->>L: 发起推荐系统与Feed流架构相关请求
    L->>L: 鉴权、校验、策略判断
    L->>R: 读取缓存、限流或临时状态
    L->>DB: 写入元数据和状态
    L->>Q: 派发异步任务
    Q->>P: 执行平台级处理
    P-->>L: 返回处理结果或指标
    L-->>U: 返回结果、降级响应或后续查询入口
```

## 7. 核心流程

### 正常流程

1. 用户或客户端发起请求。
2. Laravel 完成认证、授权、参数校验和业务策略判断。
3. Redis 处理缓存、限流、锁、短期状态或热点数据。
4. MySQL 记录核心业务状态、配置、审计和聚合结果。
5. Queue 处理异步计算、同步、聚合、导出或通知。
6. 推荐服务 执行该模块的核心平台能力。
7. 前端或后台读取结果，并通过日志、指标或通知观察处理状态。

### 异常流程

- 中间件不可用：走降级策略，使用缓存快照或返回可理解的错误。
- 队列积压：限制入口流量，降低非核心任务优先级，扩容 worker。
- 数据不一致：通过幂等键、状态机、补偿 Job 和定时校准修复。
- 权限不足：返回 403，关键动作写审计日志。
- 外部服务失败：设置 timeout、重试、熔断和人工处理入口。

## 8. Laravel 实现拆分

### 8.1 数据库表

- feed_items：user_id、video_id、source、score、cursor、exposed_at
- user_interest_profiles：user_id、tag_id、score、updated_at
- video_metrics：video_id、views、likes、completion_rate、hot_score
- user_video_impressions：user_id、video_id、feed_source、created_at

### 8.2 Model

- User hasOne InterestProfile
- Video hasOne VideoMetric
- FeedItem belongsTo User and Video

### 8.3 Controller

- FeedController@index：返回首页 Feed
- FeedFeedbackController@store：曝光、点击、不感兴趣回传
- AdminFeedController：人工置顶和精选

Controller 只负责接收请求、调用授权、组织响应；核心策略放在 Service，耗时逻辑放在 Job。

### 8.4 Service / Support / Action

- FeedRecallService：关注、同标签、热门、新视频召回
- FeedRankService：排序分计算
- FeedFilterService：去重、屏蔽、权限过滤
- FeedCursorService：游标分页

### 8.5 Job

- RefreshHotFeedCandidatesJob
- BuildUserInterestProfileJob
- AggregateFeedFeedbackJob

Job 要设置 `tries`、`timeout`、`backoff`，失败时写入状态和错误摘要，便于后台重试或人工处理。

### 8.6 Event / Listener

- VideoExposed、VideoClickedFromFeed、VideoWatchedFromFeed、UserMarkedNotInterested

### 8.7 WebSocket / Broadcasting

- 通常不需要实时广播；创作者后台可接收推荐流量摘要

### 8.8 Policy / Middleware

- 只推荐 published 且当前用户有权限观看的视频；blocked/hidden 内容必须过滤

## 9. Docker 组件选择

| 组件 | 镜像示例 | 用途 | 本地学习是否需要 |
| --- | --- | --- | --- |
| MySQL | mysql:8.0 | 业务元数据、状态、审计日志 | 需要 |
| Redis | redis:7-alpine | 缓存、限流、队列、计数、锁 | 多数模块需要 |
| Meilisearch | getmeili/meilisearch | 可选内容召回 | 后续扩展 |
| Kafka | bitnami/kafka | 推荐事件流 | 生产方向 |

## 10. 数据和状态设计

核心状态：

- feed_item：candidate -> exposed -> clicked -> watched / skipped
- video_recommend_status：indexable -> suppressed -> removed

状态设计说明：

- 状态迁移必须有明确触发者：用户、管理员、队列任务、定时任务或外部回调。
- 状态失败必须保留原因，并提供重试、跳过、回滚或人工处理方式。
- 前台页面展示业务状态，后台保留技术状态和错误详情。

## 11. Redis / Queue / WebSocket 使用方式

### 11.1 Redis

- feed:hot_videos Sorted Set 存热度候选
- feed:user:{id}:seen Set 短期去重
- feed:category:{id} 缓存分类候选
- 推荐接口限流和降级缓存

### 11.2 Queue

- 曝光和点击回传异步聚合
- 用户兴趣画像定时重建
- 热榜候选定时刷新

### 11.3 WebSocket / Reverb

- Feed 不依赖 WebSocket；推荐结果用 HTTP 拉取更稳定

## 12. 安全风险

- 推荐私有内容：召回后统一权限过滤
- 信息茧房：加入探索位和新内容扶持
- 刷指标影响排序：接入反作弊和去重
- 分页重复：使用游标和 seen 集合

## 13. 性能和扩展

- 学习版可以先用单机 Laravel + MySQL + Redis 验证流程。
- 当读多写少时，优先做缓存和 CDN。
- 当写入和事件量增加时，优先拆队列、批量写入和数据管道。
- 当单点不可接受时，增加健康检查、主从、多节点和降级开关。
- 当成本开始明显增长时，把 推荐服务 的核心指标纳入成本报表。

## 14. 监控和排查

重点指标：

- Feed CTR
- 完播率
- 人均播放时长
- 曝光回传成功率
- 召回候选数量
- 接口 P95 延迟

本地排查方式：

- `storage/logs/laravel.log`
- `php artisan queue:failed`
- `docker compose logs`
- Redis CLI 查看关键 key
- MySQL 查询状态表、日志表和慢查询
- 浏览器 Network / Console
- 后台管理页查看任务状态和指标快照

## 15. 分阶段实施路线

- Phase 1：人工精选 + 最新 + 热门混合 Feed，可手动验收
- Phase 2：多路召回、Redis 候选、曝光回传、兴趣画像
- Phase 3：特征平台、模型排序、A/B 测试和推荐效果监控

验收标准：

- Phase 1：本地能手动跑通主流程，并能说清楚核心原理。
- Phase 2：有状态表、后台入口、异步任务、指标和异常处理。
- Phase 3：能说明生产环境扩展、降级、监控、成本和风险控制。

## 16. 面试讲解角度

### 16.1 这个功能为什么要这样设计？

因为 没有推荐和 Feed，视频平台只能靠搜索、分类和热门榜，内容消费效率和创作者分发能力都不足。，所以需要平台级设计，而不是只在 Controller 中补几行逻辑。

### 16.2 Laravel 在里面负责什么？

Laravel 负责业务策略、权限、状态、元数据、后台管理、任务派发和结果查询。

### 16.3 中间件负责什么？

中间件负责高吞吐、低延迟、异步化、数据分析、媒体处理、缓存分发或基础设施能力。

### 16.4 为什么不能全部用 Laravel 直接做？

Laravel 不适合承载媒体流、大规模事件、高频实时计算、复杂调度和海量分析。它应该作为业务控制层，而不是所有基础设施的替代品。

### 16.5 如果数据量 / 流量变大，怎么扩展？

先缓存和异步化，再拆专门服务；先做单机可观察，再做多节点和容灾；先用规则可解释方案，再引入复杂平台能力。

### 16.6 失败场景怎么处理？

通过状态机、幂等键、重试、死信、补偿任务、降级开关和后台人工处理入口处理失败。

### 16.7 安全风险怎么处理？

围绕权限、输入校验、限流、签名、审计、隐私和误操作防护设计安全边界。

### 16.8 这个方案还有哪些不足？

学习版主要用于理解和面试讲解，不等价于真实大厂平台。真正生产还需要组织流程、SLA、成本预算、专门团队和长期数据积累。

## 17. 总结

推荐系统的核心不是一个排序 SQL，而是召回、排序、过滤、曝光回传和反馈学习组成的闭环。

## 一句话总结

推荐系统与Feed流架构的核心是：让 Laravel 站在业务控制层，平台组件承担高吞吐和基础设施能力，并用容量、成本、稳定性和安全边界证明设计可以从 Demo 演进到生产。
