# Senior PHP Laravel Learning Plan

本文档是本项目后续学习、复盘和面试准备任务的规划依据。后续新增示例、文档、Docker 环境、测试用例和专题复盘时，优先按照本文拆分任务。

## 1. 项目定位

当前项目是一个基于 Laravel 10 的长期学习项目，用于系统化沉淀：

- Laravel 框架组件和常用方法示例。
- PHP 新版本特性演示。
- PHP 资深工程师面试知识体系。
- 中间件、基础设施、部署、性能、安全和线上故障复盘。

项目交付形态采用“可运行示例 + 文档 + 面试问答”。示例代码需要能运行，文档需要能复盘，面试内容需要能支撑从基础追问到资深场景题。

## 2. 基本约束

- Laravel 主项目暂不升级，继续以 Laravel 10 为学习主线。
- 主运行环境使用 Docker Compose，计划基线为 PHP 8.3、MySQL 8、Redis 7、RabbitMQ、Mailpit。
- PHP 8.4/8.5 新特性作为独立专题，不能破坏 Laravel 10 主应用兼容性。
- 学习示例默认隔离并关闭，通过环境变量显式启用。
- 示例代码优先放在 `app/Learning`、`routes/interview_examples.php`、`config/interview_examples.php`、`docs` 等学习目录或文件中。
- 示例表、配置、路由、命令使用 `interview` 或 `learning` 语义前缀，避免影响真实业务结构。
- 文档使用中文，代码类名、方法名、配置键保持英文。
- 后续任务不能回滚或覆盖已有未提交改动，除非明确说明这些改动就是本次任务目标。

## 3. 学习单元标准

每个学习主题尽量按统一结构沉淀：

- 学习目标：说明为什么学习这个主题。
- 核心概念：解释框架、语言或中间件的关键机制。
- 源码路径：列出 Laravel 应用层或框架核心中最值得阅读的文件。
- 可运行入口：提供 HTTP 路由、Artisan 命令、测试用例或 CLI 示例。
- 示例代码：展示最小但完整的使用方式。
- 面试回答：整理可直接表达的回答要点。
- 资深追问：补充面试官可能继续深入的问题。
- 常见坑：记录生产实践中的风险、边界和排查方式。
- 验证方式：说明如何通过测试、命令或日志确认示例生效。

## 4. Laravel 框架核心主线

### 4.1 生命周期与入口

- HTTP 请求生命周期：`public/index.php`、`bootstrap/app.php`、HTTP Kernel、中间件管道、路由分发、Response 发送、terminate 阶段。
- Artisan 生命周期：`artisan`、Console Kernel、命令注册、命令执行、调度任务。
- 服务启动流程：配置加载、Provider 注册、Provider 启动、Facade 解析。
- Laravel 10 与 Laravel 11+ 骨架差异：Kernel、Handler、RouteServiceProvider、`bootstrap/app.php` 配置方式。

面试重点：

- Laravel 请求从入口到控制器经历哪些阶段。
- Kernel、ServiceProvider、Middleware 的职责边界。
- `register()` 和 `boot()` 的区别。
- Laravel 10 和新版本应用骨架差异。

### 4.2 路由、控制器与请求响应

- Web 路由、API 路由、路由分组、命名路由、路由模型绑定。
- Controller 单动作、多动作、依赖注入。
- Request、FormRequest、Validation、Response、JsonResource。
- 中间件别名、中间件组、终止中间件。
- 限流、签名 URL、CSRF、CORS。

面试重点：

- 路由模型绑定如何工作。
- FormRequest 相比手写 validate 的优势。
- Resource 层解决什么问题。
- Middleware 适合放什么逻辑，不适合放什么逻辑。

### 4.3 服务容器、Provider 与 Facade

- Container 绑定：`bind`、`singleton`、`scoped`、实例绑定、上下文绑定。
- 自动依赖解析、接口依赖注入、构造函数注入、方法注入。
- ServiceProvider 的注册和启动。
- Facade 静态代理机制、Facade Root、实时 Facade。
- Macro、Response Macro、Collection Macro。

面试重点：

- Laravel 容器如何解析依赖。
- 为什么要依赖接口而不是具体类。
- `singleton` 在 Octane/Swoole 下有什么风险。
- Facade 和静态方法有什么本质区别。

### 4.4 Eloquent 与数据访问

- Model、Migration、Factory、Seeder。
- 一对一、一对多、多对多、多态关联。
- Scope、Cast、Accessor、Mutator、Observer。
- 预加载、懒加载、N+1、`withCount`、`loadMissing`。
- 批量赋值、软删除、全局作用域。
- Query Builder、原生 SQL、分页、chunk、cursor、lazy。

面试重点：

- 如何排查和解决 N+1。
- Eloquent 和 Query Builder 如何取舍。
- 大数据导出为什么不能直接 `all()`。
- Model Observer 和 Event 的区别。

### 4.5 事务、锁与一致性

- 数据库事务、异常回滚、嵌套事务。
- 悲观锁：`lockForUpdate`、`sharedLock`。
- 乐观锁：版本号、更新时间戳、条件更新。
- 死锁原因、重试策略、事务粒度。
- 库存扣减、余额变更、订单支付状态流转。

面试重点：

- 如何保证订单支付回调幂等。
- 高并发扣库存如何避免超卖。
- 死锁如何排查和缓解。
- 事务里为什么不能放长时间外部调用。

### 4.6 队列、事件与异步化

- Event、Listener、Job、ShouldQueue。
- 队列连接：sync、database、redis、RabbitMQ。
- 任务重试、超时、失败任务、退避策略、唯一任务。
- 事件解耦和队列异步化边界。
- Schedule、Supervisor、Worker 平滑重启。

面试重点：

- 队列如何提升接口响应速度。
- 如何保证任务幂等。
- 任务失败后如何告警和恢复。
- 部署后为什么需要重启 queue worker。

### 4.7 认证、授权与安全入口

- Laravel Auth、Guard、Provider。
- Session 认证、Token 认证、Sanctum。
- Gate、Policy、`authorize`。
- CSRF、XSS、SQL 注入、文件上传、签名 URL。
- 密码哈希、敏感配置、审计日志。

面试重点：

- 认证和授权的区别。
- Sanctum 适合什么场景。
- 如何设计后台权限。
- 常见 Web 安全漏洞如何在 Laravel 中防护。

### 4.8 测试与质量保障

- Unit Test、Feature Test、HTTP Test。
- Database Refresh、Factory、Seeder。
- Mock、Fake、Queue fake、Event fake、Notification fake、Mail fake。
- Pint、PHPStan/Psalm、CI。
- 测试数据隔离和副作用控制。

面试重点：

- 单元测试和功能测试的边界。
- Laravel Fake 系列如何降低测试成本。
- 如何测试队列、邮件、事件。
- 如何让遗留项目逐步补测试。

## 5. PHP 高级与新版本特性主线

### 5.1 PHP 语言基础进阶

- 类型系统：标量类型、返回类型、联合类型、交叉类型、`mixed`、`never`、`static`。
- 闭包、匿名函数、箭头函数、一等可调用。
- Generator、Iterator、yield、惰性遍历。
- Trait、接口、抽象类、final、匿名类。
- Attribute、Reflection、Composer 自动加载。
- 异常体系、错误处理、类型错误。

面试重点：

- `yield` 适合解决什么问题。
- Trait 和继承的冲突如何处理。
- Composer PSR-4 自动加载原理。
- PHP 弱类型和严格类型的边界。

### 5.2 PHP 运行机制

- PHP-FPM master/worker 模型。
- 请求级生命周期和无共享架构。
- OPcache、preload、JIT。
- 内存管理、引用计数、GC。
- CLI 与 FPM 的差异。
- ini 配置、扩展加载、错误日志。

面试重点：

- PHP 为什么天然适合请求隔离。
- PHP-FPM 进程数如何估算。
- OPcache 如何提升性能。
- 内存泄漏在 PHP 项目里如何排查。

### 5.3 PHP 8.1 到 8.5 新特性

- PHP 8.1：Enum、readonly property、first-class callable、intersection types、fibers。
- PHP 8.2：readonly class、DNF types、独立 `null`/`false`/`true` 类型、动态属性弃用。
- PHP 8.3：typed class constants、`#[Override]`、只读属性克隆改进。
- PHP 8.4：property hooks、asymmetric visibility、new without parentheses。
- PHP 8.5：pipe operator、clone with、`#[NoDiscard]`、新增闭包和常量能力。

面试重点：

- Enum 比常量类有什么优势。
- readonly 解决什么问题，又有什么限制。
- Attribute 在框架中常见用途是什么。
- 新特性如何评估是否适合引入生产项目。

## 6. MySQL 主线

### 6.1 索引与 SQL 优化

- B+Tree 索引、联合索引、最左前缀。
- 覆盖索引、回表、索引下推。
- `EXPLAIN`、慢查询日志、执行计划。
- 排序、分组、分页优化。
- 大表查询、冷热数据、归档。

面试重点：

- 为什么 `like '%keyword%'` 不走普通索引。
- 联合索引字段顺序如何设计。
- 深分页如何优化。
- 慢查询如何排查。

### 6.2 事务与锁

- ACID、隔离级别、MVCC。
- 当前读、快照读。
- 行锁、间隙锁、临键锁。
- 死锁检测、锁等待超时。
- 并发扣减、唯一约束防重、幂等表。

面试重点：

- 可重复读如何避免幻读。
- MySQL 死锁如何定位。
- 唯一索引如何辅助幂等。
- 事务隔离级别如何选择。

### 6.3 架构与运维

- 主从复制、读写分离、主从延迟。
- 分库分表、分区表。
- 备份恢复、binlog、数据订正。
- 连接池、最大连接数、慢 SQL 告警。

面试重点：

- 读写分离下如何处理读延迟。
- 分库分表会带来哪些复杂度。
- 数据误删如何恢复。
- 数据库 CPU 飙高如何排查。

## 7. Redis 主线

### 7.1 数据结构与使用场景

- String、Hash、List、Set、Sorted Set。
- Bitmap、HyperLogLog、Geo。
- Stream、Consumer Group。
- TTL、过期策略、淘汰策略。
- Pipeline、Lua、事务。

面试重点：

- 各数据结构适合什么业务场景。
- Pipeline 和事务有什么区别。
- Lua 为什么能保证原子性。
- Redis Stream 和传统 MQ 的差异。

### 7.2 缓存与一致性

- Cache aside、write through、write behind。
- 缓存穿透、击穿、雪崩。
- 热 key、大 key。
- 缓存预热、缓存更新、延迟双删。
- 本地缓存与分布式缓存。

面试重点：

- 如何防止缓存击穿。
- 热 key 如何发现和治理。
- 缓存和数据库如何保持一致。
- 删除缓存失败怎么办。

### 7.3 分布式能力

- 分布式锁、锁超时、锁续期。
- 限流：计数器、滑动窗口、令牌桶。
- Pub/Sub。
- Redis 持久化：RDB、AOF。
- 主从、哨兵、Cluster。

面试重点：

- Redis 分布式锁有哪些坑。
- Redlock 是否一定可靠。
- Redis Cluster 为什么有 hash slot。
- Redis 持久化如何选择。

## 8. MQ 主线

### 8.1 RabbitMQ

- Producer、Consumer、Exchange、Queue、Binding、Routing Key。
- Direct、Topic、Fanout、Headers exchange。
- ACK、NACK、重回队列。
- 重试队列、死信队列、延迟队列。
- 消息持久化、消费者并发、prefetch。

面试重点：

- RabbitMQ 如何保证消息不丢。
- 消息重复消费如何处理。
- 死信队列适合什么场景。
- 消息堆积如何排查。

### 8.2 Kafka/RocketMQ 对比专题

- Topic、Partition、Consumer Group。
- Offset、顺序消息、批量消费。
- 高吞吐日志型消息。
- 消息堆积、重平衡、延迟。
- RabbitMQ、Kafka、RocketMQ 的选型差异。

面试重点：

- Kafka 为什么吞吐高。
- 分区和消费者组如何影响并发。
- 如何保证局部顺序。
- 业务系统如何选择 MQ。

### 8.3 Laravel 队列结合

- Laravel Queue 抽象。
- Redis queue、database queue、RabbitMQ 扩展。
- Job 幂等、唯一任务、失败重试。
- 队列监控、失败告警、补偿任务。

面试重点：

- Laravel 队列和业务 MQ 如何结合。
- 支付回调、发货、发短信如何拆异步任务。
- 如何避免任务重复造成资损。

## 9. 并发、进程、线程与协程主线

### 9.1 PHP 进程模型

- PHP-FPM master/worker。
- Worker 数量、请求排队、慢请求。
- CLI 常驻进程、队列 Worker。
- Supervisor 管理进程。
- 信号处理、平滑退出。

面试重点：

- PHP-FPM 进程数如何配置。
- 队列 Worker 为什么要限制内存和任务数。
- 常驻进程和普通请求的最大区别。

### 9.2 多进程、多线程、协程

- 进程与线程区别。
- PHP 多进程扩展和 CLI 场景。
- Swoole 协程、Channel、Coroutine HTTP client。
- Laravel Octane、Swoole、RoadRunner。
- 常驻内存下的单例、静态变量、请求污染风险。

面试重点：

- 协程和线程有什么区别。
- Octane 为什么不能随便使用请求态单例。
- 多进程任务如何切分和回收。
- 如何处理共享状态和并发安全。

## 10. Docker 与本地开发环境主线

### 10.1 Docker 基础

- Dockerfile、镜像、容器、volume、network。
- 镜像分层、构建缓存、多阶段构建。
- env、secret、healthcheck。
- 容器日志、端口映射、服务发现。

面试重点：

- 镜像和容器的区别。
- volume 解决什么问题。
- Docker 网络模式有哪些。
- 如何减小镜像体积。

### 10.2 Laravel Docker 环境

- PHP 8.3 应用容器。
- MySQL 8、Redis 7、RabbitMQ、Mailpit。
- Composer 安装、权限、缓存。
- `.env` 与容器服务名。
- 本地启动、停止、重建、日志查看。

面试重点：

- 容器内连接 MySQL 为什么不能用 `127.0.0.1`。
- 本地开发和生产镜像有什么区别。
- 如何排查容器启动失败。

### 10.3 部署进阶

- Nginx + PHP-FPM。
- 反向代理、静态资源、上传限制。
- 日志收集、健康检查、滚动发布。
- CI/CD、回滚、数据库迁移发布顺序。
- Kubernetes 作为后续扩展专题。

面试重点：

- 502 和 504 如何区分排查。
- 发布时如何避免中断队列任务。
- 数据库迁移如何安全上线。

## 11. Linux、网络与系统基础

### 11.1 Linux

- 进程、线程、文件描述符。
- 权限、用户、目录、软链接。
- systemd、cron、Supervisor。
- 日志、磁盘、内存、CPU。
- 常用排查命令：`top`、`ps`、`lsof`、`netstat`、`ss`、`df`、`du`、`tail`、`grep`。

面试重点：

- 端口占用如何排查。
- 磁盘写满如何处理。
- 进程 CPU 飙高如何定位。

### 11.2 网络

- TCP/IP、三次握手、四次挥手。
- HTTP、HTTPS、DNS。
- Keep-Alive、连接池、超时、重试。
- 反向代理、负载均衡。
- 幂等请求、重试风暴。

面试重点：

- HTTP 502、504、499 分别常见原因是什么。
- 超时应该如何分层设置。
- 重试为什么可能放大故障。

## 12. 架构设计与工程实践

### 12.1 分层与建模

- Controller、Service、Action、Repository 的取舍。
- DDD、领域服务、应用服务。
- DTO、Value Object、Enum。
- 模块边界、依赖方向。

面试重点：

- 业务复杂后如何组织 Laravel 代码。
- Repository 模式是否一定需要。
- Service 层如何避免变成事务脚本垃圾桶。

### 12.2 高可用与一致性

- 幂等设计、去重表、唯一约束。
- 最终一致性、补偿任务、对账。
- 限流、降级、熔断。
- 灰度发布、回滚。

面试重点：

- 支付系统如何保证最终一致。
- 下游接口不稳定如何保护主流程。
- 如何设计补偿任务。

### 12.3 性能优化

- 接口耗时拆解。
- SQL 优化、缓存优化、队列削峰。
- 压测、容量评估。
- Xdebug、Blackfire、Laravel Telescope/Pulse。
- 日志链路和 trace id。

面试重点：

- 慢接口如何系统排查。
- QPS 提升十倍如何改造。
- 如何判断瓶颈在 PHP、数据库、Redis 还是网络。

## 13. 安全主线

- 认证、授权、最小权限。
- CSRF、XSS、SQL 注入、SSRF。
- 文件上传、路径穿越、MIME 检查。
- 密码哈希、token 存储、签名校验。
- 敏感配置、日志脱敏。
- 审计日志和操作追踪。

面试重点：

- Laravel 如何防 SQL 注入。
- XSS 如何防护。
- SSRF 在 PHP 项目中如何出现。
- 文件上传如何设计安全校验。

## 14. 线上故障复盘主线

每类故障都要沉淀现象、影响、排查路径、根因、修复、预防和面试表达。

- 502/504、接口超时。
- MySQL 慢查询、死锁、连接耗尽。
- Redis 热 key、大 key、缓存雪崩。
- MQ 消息堆积、重复消费、死信积压。
- 队列 Worker 内存增长、任务失败。
- CPU 飙高、内存暴涨、磁盘写满。
- 部署失败、配置错误、环境变量缺失。
- 第三方接口异常、重试风暴。

面试重点：

- 线上接口突然变慢如何排查。
- 消息积压百万级如何处理。
- Redis CPU 打满如何处理。
- 数据库死锁如何复盘。

## 15. 阶段实施顺序

### Phase 1：环境与学习骨架

- 创建 Docker Compose 环境。
- 整理 `.env.example`。
- 建立学习总索引文档。
- 保持现有 LaravelInterview 示例默认隔离。
- 补充运行、测试、启停文档。

### Phase 2：Laravel 组件闭环

- 路由、Controller、Request、Resource。
- Container、Provider、Facade。
- Eloquent、Query Builder、事务、锁。
- Event、Queue、Mail、Notification。
- Auth、Gate、Policy。
- Feature/Unit 测试。

### Phase 3：基础设施专题

- Redis 缓存、锁、限流、Stream。
- RabbitMQ 队列、ACK、重试、死信。
- MySQL 索引、事务、执行计划。
- Docker 编排和日志排查。

### Phase 4：PHP 高级与新版本专题

- PHP 8.1-8.5 新特性示例。
- PHP-FPM、OPcache、GC。
- Generator、Fiber、协程、Octane。
- Composer、autoload、反射、Attribute。

### Phase 5：资深面试与故障复盘

- 架构设计题。
- 性能优化题。
- 安全题。
- 线上故障案例。
- 系统设计和追问题库。

## 16. 后续任务执行 Prompt

后续让编码代理执行任务时，可使用以下基础 prompt：

```text
你是 Codex，在 /Users/tao/workspace/code/laravel/laravel 中工作。

目标：按照 docs/senior-php-laravel-learning-plan.md 推进当前 Laravel 10 长期学习项目。

执行规则：
1. 开始前先只读检查 git status 和相关文件，不要回滚用户已有改动。
2. Laravel 10 主项目不升级。
3. 主运行环境以 PHP 8.3、MySQL 8、Redis 7、RabbitMQ、Mailpit 为基线。
4. 学习示例必须隔离，默认关闭，通过环境变量启用。
5. 示例需要同时具备源码、可运行入口、中文文档、面试问答和测试。
6. PHP 8.4/8.5 新特性必须独立处理，不能破坏 Laravel 10 主应用兼容性。
7. 每次任务完成后说明改动文件、验证命令和未完成事项。

验收标准：
- 不破坏现有 Laravel 项目启动。
- 不覆盖无关未提交改动。
- 文档与代码路径相互对应。
- 示例能通过命令、HTTP 路由或测试验证。
```
