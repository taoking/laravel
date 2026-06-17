# Phase 10: Mainstream Video / Live Platform Roadmap

本文分析当前 Laravel 学习项目与主流视频分享网站、直播网站之间的差距，并整理后续可以推进的功能方向和技术方向。

目标不是立刻做成 YouTube、Bilibili、Twitch、Douyin 级别的平台，而是把复杂系统拆成可以学习、可以验收、可以面试讲解的小阶段。

## 1. 当前项目基线

当前项目已经具备：

- 视频上传。
- 原始 MP4 播放。
- FFmpeg / ffprobe 队列异步处理。
- 封面图、时长、分辨率、编码信息提取。
- 单码率 HLS fallback。
- 360p / 720p Adaptive HLS。
- `video_renditions` 清晰度状态表。
- 直播房间。
- 本地 HLS 伪直播。
- MediaMTX + OBS -> RTMP -> HLS 的真实直播学习链路。
- Laravel Reverb + Echo 实时聊天。
- MySQL / Redis / MediaMTX Docker Compose 本地中间件。

当前项目仍然是学习型系统，不具备主流平台的内容推荐、审核、版权、风控、大规模分发、商业化和生产级可观测性。

## 2. 主流平台能力观察

### 视频分享网站常见能力

以 YouTube、Bilibili、TikTok / Douyin、Vimeo 等平台为参考，视频分享网站通常包含：

- 创作者上传和内容管理。
- 转码、封面、字幕、多语言音轨。
- 多码率播放和跨设备适配。
- 视频列表、频道、合集、播放列表。
- 搜索、标签、分类、推荐。
- 评论、点赞、收藏、分享、关注。
- 数据统计：播放量、观看时长、完播率、互动率。
- 内容审核：机器审核、人工审核、举报处理。
- 版权保护：内容指纹、版权声明、下架流程。
- 广告、会员、付费内容、创作者收益。
- CDN、缓存、边缘分发和播放质量监控。

### 直播网站常见能力

以 Twitch、YouTube Live、Bilibili Live、Douyin Live 等平台为参考，直播网站通常包含：

- 直播预约、开播、结束和回放。
- 推流地址和 stream key 管理。
- 直播转码和多码率输出。
- 直播低延迟模式选择。
- 实时聊天、弹幕、点赞、礼物、关注、订阅。
- 在线人数和观众列表。
- 主播后台：推流状态、码率、帧率、丢帧、延迟。
- 管理员 / 房管：禁言、踢人、关键词过滤。
- 直播推荐流、分类、标签、排行榜。
- 直播录制、切片、精彩片段、回放生成。
- 风控：刷屏、恶意消息、违规内容、异常流量。
- 观众端播放质量监控和自动降级。

## 3. 当前项目与主流平台差距

| 方向 | 当前项目 | 主流平台 | 后续学习价值 |
| --- | --- | --- | --- |
| 用户体系 | 基础用户表，未强制登录 | 创作者、观众、管理员、房管、实名/风控 | 学权限、Policy、Gate |
| 上传 | 单文件上传 | 断点续传、秒传、批量、移动端上传 | 学分片上传、临时文件、任务合并 |
| 转码 | FFmpeg 本地转码 | 转码集群、任务调度、多清晰度、多格式 | 学队列、状态机、任务拆分 |
| 播放 | MP4 + HLS | ABR、DRM、字幕、多音轨、质量监控 | 学播放器协议和前端事件 |
| 直播 | MediaMTX 本地 RTMP -> HLS | 推流鉴权、多协议、低延迟、录制回放 | 学媒体服务器边界 |
| 聊天 | Reverb 文本聊天 | 弹幕、礼物、房管、敏感词、限流 | 学 WebSocket 和实时风控 |
| 推荐 | 无 | 首页 Feed、相似视频、关注流、热榜 | 学搜索、排序、数据分析 |
| 审核 | 无 | 机审、人工审核、举报、版权 | 学后台流程和状态设计 |
| 存储 | 本地磁盘 | 对象存储、冷热分层、CDN | 学文件生命周期 |
| 可观测性 | 测试 + 日志 | 指标、Tracing、队列监控、播放 QoE | 学生产运维意识 |

## 4. 功能方向路线图

### P0：权限和基础安全

推荐优先做。理由是当前项目已经能上传、转码、直播和聊天，但缺少清晰权限边界。

可实现功能：

- 登录用户才能上传视频。
- 登录用户才能创建直播房间。
- 房主才能编辑房间、开始直播、结束直播。
- HLS 文件访问校验视频归属或公开视频状态。
- 聊天消息记录用户 ID。

技术落点：

- Laravel Breeze 或手写最小登录流程。
- Policy / Gate。
- FormRequest authorize。
- Feature Test 覆盖 403。

面试讲解点：

- 为什么视频文件路由也要鉴权。
- 为什么直播房间需要 owner。
- private channel / presence channel 和登录用户的关系。

### P1：视频管理后台

当前视频上传后只能列表和详情查看，缺少内容管理能力。

可实现功能：

- 视频编辑：标题、简介、标签、分类。
- 视频上下架：draft / published / hidden / blocked。
- 删除视频时清理原文件、封面、HLS、renditions。
- 重新转码按钮。
- 转码失败后重试。

技术落点：

- `videos.visibility` 或 `publish_status` 字段。
- Controller 小范围扩展。
- 删除文件用 Storage。
- Queue retry / failed_jobs。

面试讲解点：

- 视频删除为什么不能只删数据库。
- 转码失败为什么要支持重试。
- 内容状态和处理状态为什么要分开。

### P2：上传体验升级

主流平台不会把大文件上传完全压在一次普通表单提交上。

可实现功能：

- 上传进度条。
- 前端大文件分片上传。
- 后端分片合并。
- 上传前校验文件大小和 MIME。
- 上传后异步创建处理任务。

技术落点：

- Blade + 原生 JS `XMLHttpRequest` 或 `fetch` + progress。
- `video_upload_sessions` 表。
- `video_upload_chunks` 目录。
- 合并完成后创建 `videos` 记录。

学习价值：

- 大文件上传和普通表单上传的区别。
- 临时文件清理。
- 幂等合并。

### P3：播放体验增强

当前播放器能播 HLS，但还没有主流播放器体验。

可实现功能：

- 清晰度选择菜单。
- 播放进度记录。
- 倍速播放。
- 试看 / 断点续播。
- 字幕上传和 WebVTT 显示。
- 播放错误提示。

技术落点：

- hls.js quality levels。
- `video_watch_progress` 表。
- `video_subtitles` 表。
- 前端监听 `timeupdate`、`error`、`loadedmetadata`。

面试讲解点：

- HLS ABR 自动切换和手动清晰度选择的区别。
- 为什么播放进度不能每秒写库。
- 字幕为什么适合单独建表和单独文件。

### P4：搜索、标签和分类

主流视频平台的核心体验不是“上传后能播”，而是“用户能找到内容”。

可实现功能：

- 视频标签。
- 分类。
- 标题 / 简介搜索。
- 热门视频。
- 最新视频。
- 观看历史。

技术落点：

- `tags`、`video_tag`、`categories`。
- MySQL fulltext 或简单 LIKE 学习版。
- 统计表：`video_metrics`。

后续可选：

- Meilisearch / Elasticsearch，但学习项目不建议太早引入。

### P5：评论、互动和社区

视频分享网站通常有评论，直播网站通常有聊天和弹幕。

可实现功能：

- 视频评论。
- 评论回复。
- 点赞 / 收藏。
- 关注创作者。
- 举报评论。
- 房间弹幕模式。

技术落点：

- `comments` 表。
- `likes` 多态表或明确业务表。
- `follows` 表。
- Reverb 广播弹幕。

面试讲解点：

- 评论和直播聊天为什么不是同一张表。
- 点赞高并发如何做幂等。
- 弹幕和聊天消息展示模型有什么区别。

### P6：直播互动增强

当前直播间只有基础聊天，主流直播网站会强化互动。

可实现功能：

- 在线人数。
- 进入 / 离开提示。
- 禁言用户。
- 房管角色。
- 敏感词过滤。
- 消息限流。
- 点赞飘屏。
- 简单礼物事件，不做支付。

技术落点：

- Presence channel。
- Redis 记录在线用户和房间计数。
- `chat_moderation_actions` 表。
- `muted_users` 表。
- Laravel RateLimiter。
- `ChatMessageCreated` / `UserMuted` / `RoomLiked` 事件。

面试讲解点：

- 在线人数为什么不适合只靠数据库。
- 聊天限流为什么要靠 Redis。
- 礼物事件和支付结算为什么要分阶段设计。

### P7：真实直播能力增强

当前 MediaMTX 只是本地推流学习版。

可实现功能：

- stream key 重置。
- 推流鉴权。
- 开播状态自动检测。
- MediaMTX hook 回调。
- 直播录制。
- 回放生成。
- 直播截图封面。
- 多码率直播输出。

技术落点：

- MediaMTX 配置 hook。
- `live_stream_events` 表。
- `live_recordings` 表。
- 后台 Job 处理录制文件。
- Media server 与 Laravel 通过 HTTP callback 通信。

面试讲解点：

- Laravel 为什么不接收 RTMP。
- 推流鉴权怎么做。
- 直播回放为什么是异步生成。

### P8：内容审核和风控

主流平台必须处理违规内容和恶意行为。

可实现功能：

- 视频审核状态：pending_review / approved / rejected。
- 直播间举报。
- 聊天举报。
- 敏感词命中记录。
- 管理后台处理举报。
- 用户封禁。

技术落点：

- `moderation_reports` 表。
- `moderation_actions` 表。
- `blocked_words` 表。
- 后台列表和状态流转。

注意：

- 学习项目可以先做规则和流程，不接外部 AI 审核服务。
- 生产环境才考虑机审、人审平台、版权识别等复杂能力。

### P9：数据统计和创作者后台

主流平台会给创作者反馈数据。

可实现功能：

- 视频播放次数。
- 独立观众数。
- 观看时长。
- 完播率。
- 直播间峰值在线。
- 聊天消息数。
- 新增粉丝数。

技术落点：

- 前端播放器事件上报。
- `video_play_events` 表。
- 聚合 Job。
- `video_daily_metrics` 表。
- `live_room_metrics` 表。

面试讲解点：

- 明细事件和聚合数据为什么要分开。
- 播放量如何防刷。
- 为什么统计写入不能阻塞播放。

### P10：文件存储和分发

当前使用本地 `storage`，适合学习，不适合生产。

可实现功能：

- 文件删除和清理。
- HLS 文件生命周期管理。
- 切换到 S3 / MinIO 兼容对象存储。
- CDN URL 生成。
- 私有文件签名 URL。

技术落点：

- Laravel Filesystem disk。
- MinIO 本地 Docker。
- signed route。
- 文件清理 Job。

注意：

- 本项目之前原则是不为了早期阶段引入 MinIO。现在可作为 P10，等核心业务稳定后再做。

### P11：可观测性和稳定性

主流平台会持续监控转码、推流、播放和聊天。

可实现功能：

- 队列失败面板。
- 转码耗时统计。
- FFmpeg stderr 摘要。
- Reverb 连接状态日志。
- MediaMTX 容器状态检查。
- 播放错误上报。
- m3u8 / segment 请求耗时统计。

技术落点：

- Laravel failed_jobs。
- 自定义 `video_processing_logs` 表。
- Laravel Telescope，本地学习可选。
- Prometheus / Grafana，生产学习可选。
- 播放器 error event 上报。

面试讲解点：

- 如何定位“用户说视频不能播”。
- 如何判断是转码失败、HLS 文件缺失、CDN 缓存、浏览器不支持还是权限 403。

## 5. 技术架构推进方向

### 5.1 任务拆分

当前：

```text
ProcessUploadedVideo -> GenerateAdaptiveHlsForVideo
```

后续：

```text
UploadCompleted
-> ProbeVideo
-> GenerateThumbnail
-> GenerateRendition(360p)
-> GenerateRendition(720p)
-> BuildMasterPlaylist
-> PublishVideo
```

学习价值：

- 更清楚地理解队列任务粒度。
- 单个 rendition 失败可以独立重试。
- 更接近生产转码流水线。

建议阶段：

- 当前先不要拆。
- 当新增 1080p、字幕、截图、回放后，再拆。

### 5.2 状态机

当前状态主要是字符串字段。

后续可以整理为明确状态机：

- 视频处理状态：pending / processing / ready / failed。
- HLS 状态：pending / processing / ready / partial_ready / failed。
- 内容发布状态：draft / published / hidden / blocked。
- 直播状态：scheduled / live / ended / interrupted。

学习价值：

- 面试中可以讲清楚“处理状态”和“业务可见状态”的区别。
- 避免一个 `status` 字段承担太多语义。

### 5.3 播放链路

当前：

```text
Browser -> Laravel HLS route -> storage/app/public/videos/hls
```

后续：

```text
Browser -> CDN / Object Storage -> HLS files
Laravel -> permission / signed URL / metadata
```

学习项目中，Laravel 路由代理 HLS 方便做鉴权和理解路径安全。生产中，大规模 HLS segment 不应该全部经过 Laravel。

### 5.4 直播链路

当前：

```text
OBS -> MediaMTX RTMP -> MediaMTX HLS -> Browser
Laravel -> room / playback_url / chat
```

后续：

```text
OBS -> Media Server
Media Server -> transcode / record / callback
Laravel -> auth / room / chat / metrics / replay
Browser -> HLS or WebRTC
```

Laravel 的边界要保持清楚：

- 不处理 RTMP。
- 不转发大规模媒体流。
- 不直接做实时转码。
- 做业务控制层。

### 5.5 聊天链路

当前：

```text
HTTP POST -> chat_messages -> MessageSent -> Reverb -> Echo
```

后续：

```text
HTTP POST
-> auth / rate limit / sensitive word / mute check
-> chat_messages
-> broadcast
-> client render
-> metrics aggregation
```

学习价值：

- WebSocket 不等于不需要 HTTP。
- 消息先入库再广播。
- 实时系统也需要审计和回放。

## 6. 可作为阶段任务的后续规划

### Phase 11：用户权限和房主控制

目标：

- 登录用户上传视频。
- 登录用户创建房间。
- 房主修改房间。
- 房主开始 / 结束直播。
- HLS 路由加权限。

技术点：

- Auth。
- Policy。
- Feature Test。

### Phase 12：视频管理和删除清理

目标：

- 编辑视频信息。
- 删除视频。
- 删除时清理原视频、封面、HLS、renditions。
- 重新转码。

技术点：

- Storage cleanup。
- DB transaction。
- Queue retry。

### Phase 13：播放器增强

目标：

- 播放进度记录。
- 清晰度手动选择。
- 播放错误上报。
- 字幕 WebVTT。

技术点：

- hls.js event。
- 前端节流。
- `video_watch_progress`。
- `video_subtitles`。

### Phase 14：直播房管和聊天治理

目标：

- 禁言。
- 敏感词。
- 消息限流。
- 在线人数。
- 房管角色。

技术点：

- Redis。
- Laravel RateLimiter。
- Presence channel。
- Moderation tables。

### Phase 15：MediaMTX 推流鉴权和回调

目标：

- stream key 重置。
- 推流鉴权。
- MediaMTX hook 回调 Laravel。
- 自动更新房间 live / ended 状态。

技术点：

- MediaMTX config。
- HTTP callback。
- `live_stream_events`。

### Phase 16：直播录制和回放

目标：

- MediaMTX 录制。
- 录制文件入库。
- 回放转 HLS。
- 房间结束后生成回放。

技术点：

- 文件扫描 / callback。
- Queue。
- 复用视频处理流水线。

### Phase 17：搜索、标签和推荐雏形

目标：

- 标签。
- 分类。
- 搜索。
- 热门列表。
- 观看历史。

技术点：

- MySQL fulltext / LIKE。
- metrics 聚合。
- 简单排序公式。

### Phase 18：内容审核和举报

目标：

- 视频审核状态。
- 聊天举报。
- 直播间举报。
- 管理后台处理。

技术点：

- 状态流转。
- 管理后台 Blade。
- 审核日志。

### Phase 19：对象存储和 CDN 预演

目标：

- MinIO 本地对象存储。
- HLS 文件上传到对象存储。
- signed URL。
- CDN 架构文档。

技术点：

- Laravel Filesystem S3 disk。
- 文件生命周期。
- 私有文件访问。

### Phase 20：可观测性

目标：

- 队列失败列表。
- 转码耗时统计。
- 播放错误上报。
- 直播推流状态。
- 房间指标。

技术点：

- failed_jobs。
- metrics tables。
- 日志结构化。
- Dashboard。

## 7. 不建议近期做的内容

这些方向很有价值，但不适合在当前学习项目马上做：

- 支付和真实礼物结算。
- 复杂推荐系统。
- DRM。
- 版权指纹。
- 大规模 CDN 调度。
- 多机转码集群。
- WebRTC 连麦。
- App 端推流 SDK。
- 全量微服务拆分。
- Kubernetes 生产部署。

理由：

- 这些内容依赖大量业务、合规、基础设施和成本。
- 过早引入会让学习项目失去可读性。
- 当前更应该先把 Laravel、队列、HLS、WebSocket、媒体服务器边界学扎实。

## 8. 面试讲解主线

可以把这个项目讲成三条主线。

### 视频点播主线

用户上传视频后，Laravel 只保存文件和数据库记录，然后派发队列。队列调用 ffprobe 提取 metadata，调用 FFmpeg 生成封面和 HLS。HLS 采用 master playlist + rendition playlist，浏览器用 hls.js 播放。

关键词：

- 异步处理。
- FFmpeg。
- HLS。
- Adaptive Bitrate。
- Storage。
- Queue。

### 直播主线

Laravel 不直接处理直播流。OBS 推流到 MediaMTX，MediaMTX 输出 HLS，Laravel 只保存房间、推流配置、播放地址和聊天。房间页播放 MediaMTX HLS，并通过 Reverb 做实时聊天。

关键词：

- RTMP。
- Media server。
- HLS live。
- Laravel 业务控制层。
- stream key。
- playback URL。

### 实时互动主线

聊天消息通过 HTTP 接口入库，然后广播事件到 Reverb，前端 Echo 接收后实时追加。后续可以加入 presence 在线人数、限流、敏感词和房管。

关键词：

- WebSocket。
- Broadcasting。
- Reverb。
- Echo。
- 先入库再广播。
- 在线状态。

## 9. 推荐近期优先级

如果继续推进，建议顺序：

1. Phase 11：用户权限和房主控制。
2. Phase 12：视频管理和删除清理。
3. Phase 13：播放器增强。
4. Phase 14：直播房管和聊天治理。
5. Phase 15：MediaMTX 推流鉴权和回调。
6. Phase 16：直播录制和回放。

这条路线最适合当前项目：每一步都能验收，每一步都贴近主流平台，又不会把系统做散。

## 10. 参考资料

以下资料用于观察主流平台公开能力和协议边界：

- Apple HLS 官方文档：<https://developer.apple.com/streaming/>
- Apple HLS authoring specification：<https://developer.apple.com/documentation/http-live-streaming/hls-authoring-specification-for-apple-devices>
- YouTube 直播延迟说明：<https://support.google.com/youtube/answer/7444635>
- YouTube Live Chat Messages API：<https://developers.google.com/youtube/v3/live/docs/liveChatMessages>
- Twitch Developer Documentation：<https://dev.twitch.tv/docs>
- Twitch EventSub：<https://dev.twitch.tv/docs/eventsub/>
- Bilibili 直播开放文档：<https://open-live.bilibili.com/document/>
- 抖音直播 SDK 功能介绍：<https://open.douyin.com/platform/resource/docs/ability/douyin-live-sdk/introduction>
- MediaMTX 项目文档：<https://github.com/bluenviron/mediamtx>

