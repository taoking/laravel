# 【编码阶梯 CMAF 与低延迟播放】技术方案设计

> 来源：根据 `docs/live-video-chat/11-senior-backend-interview-system-design.md` 的 `0.3 资深后端面试视角下仍需补充的方向`，并参考 `docs/live-video-chat/prompt.md` 的输出规范整理。

## 1. 模块定位

编码阶梯 CMAF 与低延迟播放属于 **技术功能模块**。

大平台不只关注能转码，还要控制画质、码率、成本、封装格式、终端兼容性和低延迟播放体验。

它涉及的核心组件是：FFmpeg、HLS、CMAF/fMP4、编码阶梯配置、video_renditions、播放器能力检测。

## 2. 解决的问题

- 用户层面：用户在不同网络和设备上都能获得合适清晰度、更快起播、更少卡顿和更低延迟。
- 系统层面：为不同分辨率、码率、编码格式和封装格式设计可扩展的转码阶梯。
- 如果不做：固定 360p/720p 难以适配真实业务，可能浪费带宽或画质不足，也无法解释 CMAF/LL-HLS 等生产话题。
- 在视频 / 直播 / 聊天平台中的位置：位于上传转码和播放分发之间，直接影响存储成本、带宽成本和播放体验。

## 3. 常见方案对比

| 方案 | 核心思路 | 需要组件 | 优点 | 缺点 | 适合阶段 | 是否推荐 |
| --- | --- | --- | --- | --- | --- | --- |
| 固定 HLS TS 阶梯 | 生成 360p/720p/1080p TS 分片 | FFmpeg、HLS | 兼容性好，易学习 | 延迟和文件数较高 | 学习版 | 推荐起步 |
| 自适应编码阶梯 | 按源视频分辨率和码率决定输出档位 | FFmpeg、配置表 | 节省成本，画质更合理 | 策略更复杂 | 项目展示版 | 推荐 |
| CMAF/fMP4 | HLS 和 DASH 共用 fMP4 分片 | CMAF、播放器 | 多协议复用，低延迟基础 | 兼容性要验证 | 中型业务 | 生产常见 |
| 硬件编码/云转码 | 使用 GPU 或云转码模板 | GPU/云点播 | 速度快，成本可控 | 依赖基础设施 | 生产环境 | 可选 |

## 4. 推荐学习方案

当前学习项目推荐方案：

```text
方案名称：配置化编码阶梯 + HLS TS 学习版 + CMAF 生产预留
核心组件：encoding_profiles、video_renditions、FFmpeg、hls.js
Laravel 职责：维护编码模板、派发转码任务、记录 rendition 状态、给播放器返回 master playlist
中间件职责：FFmpeg 执行编码封装，存储/CDN 分发输出文件
数据流：上传完成 -> ffprobe 分析源视频 -> 选择编码模板 -> 生成 renditions -> build master playlist -> 播放器 ABR
适合原因：当前项目已有 HLS 和 FFmpeg，可以平滑改成配置化阶梯
不足：本地机器转码速度和编码质量有限
后续升级：CMAF、LL-HLS、AV1/HEVC、云转码或 GPU worker
```

## 5. 生产环境方案、容量和架构设计

### 5.1 生产环境常见方案

| 方案 | 典型架构 | 适合规模 | 优点 | 缺点 | 成本 | 维护难度 | 是否推荐 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 小型业务方案 | Laravel + MySQL + Redis，转码编码策略服务 以规则和配置为主 | 学习项目到小型产品 | 实现成本低，易排查 | 能力边界有限 | 低 | 低 | 推荐学习 |
| 中型业务方案 | FFmpeg、HLS、CMAF/fMP4、编码阶梯配置、video_renditions、播放器能力检测，增加异步任务、缓存、后台和指标 | 稳定增长业务 | 可扩展，可运营 | 需要监控和运维 | 中 | 中 | 推荐演进 |
| 大型业务方案 | 转码编码策略服务 平台化，接入事件流、OLAP、调度、自动化治理 | 高并发平台 | 能力完整，抗风险强 | 复杂度和成本高 | 高 | 高 | 只做设计理解 |
| 云服务方案 | 使用云厂商托管能力或第三方 SaaS | 快速上线或团队较小 | 省运维，稳定 | 厂商绑定和费用不可忽视 | 中到高 | 低到中 | 生产可选 |
| 自建方案 | 自建数据、调度、风控、媒体或分析平台 | 有基础设施团队 | 可控性强 | 建设周期长 | 高 | 高 | 大型团队再考虑 |

### 5.2 生产环境容量估算

需要提前估算：

- 每日上传时长、平均源码率、输出档位数量、转码 worker 数、存储膨胀比例、播放终端能力

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
| 1080p/4K 转码耗时长 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |
| 多档输出导致存储增长 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |
| CMAF 兼容性测试不足 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |
| 转码队列积压 | 延迟升高、错误增加、成本上升或数据不准 | 访问量、数据量或组件能力超过当前设计 | 加缓存、异步化、分层拆分、容量规划、监控告警和降级策略 |

### 5.5 学习版到生产版的演进路线

- 本地学习版：用 Laravel、MySQL、Redis 和少量配置跑通核心流程。
- 项目展示版：增加状态表、后台入口、Redis 缓存/队列、指标和失败重试。
- 小型生产版：接入对象存储、CDN、Nginx、Supervisor、告警和备份。
- 中大型生产版：按能力拆分专门服务，接入 Kafka、OLAP、调度、风控、HA 和成本治理。
- 云服务方案：优先使用云托管能力降低运维难度，再保留业务抽象避免强绑定。

### 5.6 生产环境面试讲解

如果从 Demo 升级到生产，我会先定义容量指标和故障边界，再把同步链路压到最短；Laravel 只做业务判断和元数据更新，吞吐型任务交给中间件或专门服务。这个模块最容易被追问的是：1080p/4K 转码耗时长、多档输出导致存储增长、CMAF 兼容性测试不足，所以需要提前准备降级、监控和成本口径。

## 6. 系统架构图

```mermaid
graph LR
    client[客户端]
    laravel[Laravel业务层]
    mysql[MySQL元数据]
    redis[Redis缓存状态]
    queue[异步队列]
    worker[后台Worker]
    platform[转码编码策略服务]

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
    participant P as 转码编码策略服务
    participant DB as MySQL

    U->>L: 发起编码阶梯 CMAF 与低延迟播放相关请求
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
6. 转码编码策略服务 执行该模块的核心平台能力。
7. 前端或后台读取结果，并通过日志、指标或通知观察处理状态。

### 异常流程

- 中间件不可用：走降级策略，使用缓存快照或返回可理解的错误。
- 队列积压：限制入口流量，降低非核心任务优先级，扩容 worker。
- 数据不一致：通过幂等键、状态机、补偿 Job 和定时校准修复。
- 权限不足：返回 403，关键动作写审计日志。
- 外部服务失败：设置 timeout、重试、熔断和人工处理入口。

## 8. Laravel 实现拆分

### 8.1 数据库表

- encoding_profiles：name、codec、container、ladder_json、enabled
- video_renditions：video_id、profile_id、quality、bitrate、codec、container、status
- transcode_cost_logs：video_id、duration、cpu_seconds、output_size

### 8.2 Model

- EncodingProfile hasMany VideoRendition
- Video hasMany VideoRendition

### 8.3 Controller

- AdminEncodingProfileController：管理编码模板
- VideoPlaybackManifestController：返回播放清单

Controller 只负责接收请求、调用授权、组织响应；核心策略放在 Service，耗时逻辑放在 Job。

### 8.4 Service / Support / Action

- EncodingLadderSelector：根据源视频选择档位
- FfmpegCommandBuilder：生成安全命令参数
- ManifestBuilder：生成 master.m3u8 或 CMAF manifest

### 8.5 Job

- GenerateRenditionJob
- BuildMasterPlaylistJob
- AnalyzeTranscodeCostJob

Job 要设置 `tries`、`timeout`、`backoff`，失败时写入状态和错误摘要，便于后台重试或人工处理。

### 8.6 Event / Listener

- EncodingProfileSelected、RenditionGenerated、RenditionFailed

### 8.7 WebSocket / Broadcasting

- private-user.{id} 推送转码完成；后台可推送转码成本异常

### 8.8 Policy / Middleware

- 只有管理员可修改编码模板；公开视频只暴露可播放 rendition

## 9. Docker 组件选择

| 组件 | 镜像示例 | 用途 | 本地学习是否需要 |
| --- | --- | --- | --- |
| MySQL | mysql:8.0 | 业务元数据、状态、审计日志 | 需要 |
| Redis | redis:7-alpine | 缓存、限流、队列、计数、锁 | 多数模块需要 |
| FFmpeg | jrottenberg/ffmpeg | 本地转码和封装验证 | 需要 |
| MinIO | minio/minio | 输出文件存储 | 后续扩展 |

## 10. 数据和状态设计

核心状态：

- rendition.status：pending -> processing -> ready / failed / skipped
- profile.status：draft -> active -> disabled

状态设计说明：

- 状态迁移必须有明确触发者：用户、管理员、队列任务、定时任务或外部回调。
- 状态失败必须保留原因，并提供重试、跳过、回滚或人工处理方式。
- 前台页面展示业务状态，后台保留技术状态和错误详情。

## 11. Redis / Queue / WebSocket 使用方式

### 11.1 Redis

- 转码任务锁
- profile 配置缓存
- 转码队列积压指标

### 11.2 Queue

- 每个 rendition 独立 Job
- 高分辨率和低分辨率可分队列
- 失败后可单档重试

### 11.3 WebSocket / Reverb

- 可用于创作者后台显示转码进度，不参与播放主链路

## 12. 安全风险

- FFmpeg 参数注入：命令参数数组化
- 不合理码率浪费带宽：profile 审核和成本监控
- 终端不兼容：保留 HLS TS fallback
- 转码失败：单 rendition 独立重试

## 13. 性能和扩展

- 学习版可以先用单机 Laravel + MySQL + Redis 验证流程。
- 当读多写少时，优先做缓存和 CDN。
- 当写入和事件量增加时，优先拆队列、批量写入和数据管道。
- 当单点不可接受时，增加健康检查、主从、多节点和降级开关。
- 当成本开始明显增长时，把 转码编码策略服务 的核心指标纳入成本报表。

## 14. 监控和排查

重点指标：

- 转码耗时
- 输出文件大小
- 每分钟视频转码 CPU 成本
- 各清晰度播放占比
- 卡顿率和首帧耗时

本地排查方式：

- `storage/logs/laravel.log`
- `php artisan queue:failed`
- `docker compose logs`
- Redis CLI 查看关键 key
- MySQL 查询状态表、日志表和慢查询
- 浏览器 Network / Console
- 后台管理页查看任务状态和指标快照

## 15. 分阶段实施路线

- Phase 1：固定 360p/720p HLS TS，记录 rendition 元数据
- Phase 2：配置化编码阶梯、按源视频动态选择档位、成本日志
- Phase 3：CMAF/fMP4、LL-HLS、HEVC/AV1、云转码或 GPU 转码

验收标准：

- Phase 1：本地能手动跑通主流程，并能说清楚核心原理。
- Phase 2：有状态表、后台入口、异步任务、指标和异常处理。
- Phase 3：能说明生产环境扩展、降级、监控、成本和风险控制。

## 16. 面试讲解角度

### 16.1 这个功能为什么要这样设计？

因为 固定 360p/720p 难以适配真实业务，可能浪费带宽或画质不足，也无法解释 CMAF/LL-HLS 等生产话题。，所以需要平台级设计，而不是只在 Controller 中补几行逻辑。

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

编码策略的核心是在画质、带宽、存储和转码成本之间取平衡，并把阶梯模板配置化，而不是写死几个 FFmpeg 命令。

## 一句话总结

编码阶梯 CMAF 与低延迟播放的核心是：让 Laravel 站在业务控制层，平台组件承担高吞吐和基础设施能力，并用容量、成本、稳定性和安全边界证明设计可以从 Demo 演进到生产。
