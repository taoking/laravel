# Laravel 13 资深 PHP 学习项目知识架构

本文是当前 Laravel 13 项目的长期学习和面试准备总纲。目标不是单纯“学 Laravel”，而是用一个接近企业后台复杂度的 Laravel 13 项目，把 PHP 语言、运行机制、Laravel 源码、数据库、缓存、队列、工程化、架构设计、安全、性能和部署运维串起来。

## 1. 基线决策

- 主框架：Laravel 13。
- 主运行版本：PHP 8.4。
- 兼容学习：补齐 PHP 7.4、8.0、8.1、8.2、8.3、8.4、8.5 差异。
- 产品形态：Inertia 管理后台。
- 首期范围：只写文档计划，不实现业务代码。
- 项目方向：多数据源指标分析与内容管理平台。
- 执行计划：`docs/implementation-execution-plan.md`。
- 官方资料：
  - Laravel 13 Release Notes：https://laravel.com/docs/13.x/releases
  - Laravel 13 Upgrade Guide：https://laravel.com/docs/13.x/upgrade

Laravel 13 官方要求 PHP 8.3+，本项目选择 PHP 8.4 作为主运行版本。PHP 8.5 作为新特性和兼容性补充学习，不作为首期主运行版本，避免扩展、镜像和依赖生态在首期引入额外不确定性。

后续开发以 `docs/implementation-execution-plan.md` 为实施依据。本文负责知识架构和学习范围，执行计划负责阶段任务、路由、API、访问路径、验收标准和完成定义。

## 2. 项目定位

项目建议做成：

```text
多数据源指标分析与内容管理平台
```

它比普通博客更适合作为资深 PHP 面试项目，因为它天然包含权限、数据建模、复杂查询、异步导入、缓存、报表、审计、性能优化和部署运维。

核心模块规划：

| 模块 | 练习能力 |
| --- | --- |
| 登录注册、RBAC 权限 | Auth、Guard、Policy、Middleware、Session、CSRF、Token |
| 用户、角色、菜单、按钮权限 | Eloquent、Migration、Seeder、缓存、数据范围 |
| 指标库管理 | 表设计、索引、事务、复杂查询、导入导出 |
| 数据查询 API | FormRequest、Resource、JSON API、分页、筛选、排序 |
| 异步导入任务 | Queue、Job、Redis、失败重试、幂等 |
| 定时统计任务 | Schedule、Command、锁、任务补偿 |
| 操作日志、审计日志 | Event、Listener、Observer、Trait、Trace ID |
| 文件上传 | Storage、MinIO/S3、本地磁盘、权限控制 |
| 缓存加速 | Redis、Cache、穿透、击穿、雪崩、热点 Key |
| 性能压测 | PHP-FPM、OPcache、SQL 优化、Nginx、wrk/JMeter |
| Docker 部署 | Nginx、PHP-FPM、MySQL、Redis、Queue Worker |
| AI/语义搜索扩展 | Laravel AI SDK、向量搜索、语义检索，作为后期加分模块 |

## 3. 12 层知识体系

每层都要最终沉淀为“文档 + 代码实例 + 测试/命令 + 面试问答”。首期只完成文档计划，后续阶段再逐步实现代码。

### 3.1 PHP 语言基础与底层语义

学习目标：

- 能解释变量、类型、弱类型转换、数组 HashTable、引用、写时复制。
- 能写出 `==`、`===`、`isset`、`empty`、`is_null` 的边界示例。
- 能说明闭包、Generator、Trait、Interface、Abstract Class、Magic Methods 的真实应用。
- 能掌握 Attribute、Enum、Readonly、Union Type、Intersection Type、Throwable 等现代 PHP 能力。

项目实例方向：

- 用 Enum 表达指标状态、导入状态、审计动作。
- 用 readonly DTO 承载查询条件和导入结果。
- 用 Generator / LazyCollection 处理大文件导入。
- 用 Attribute 标记审计字段、权限点或导出字段。

面试问题：

- PHP 数组为什么既能当数组又能当 Map？
- PHP 变量赋值什么时候复制？
- 引用和对象赋值有什么区别？
- Trait 方法冲突如何解决？
- PHP 8 相比 PHP 7 的关键变化是什么？

### 3.2 PHP 运行机制

学习目标：

- 理解 CLI 与 FPM 的生命周期差异。
- 理解 Nginx、FastCGI、PHP-FPM、Laravel 的请求链路。
- 掌握 PHP-FPM master/worker、`pm = static / dynamic / ondemand`。
- 理解 OPcache、JIT、memory_limit、max_execution_time、Composer autoload 对性能的影响。

项目实例方向：

- 文档化一次 Laravel 请求从浏览器到 Controller 的完整链路。
- 为导入 worker 设计内存限制、超时和重启策略。
- 编写 OPcache/JIT/Composer autoload 运行状态检查命令。

面试问题：

- 一个 Laravel 请求从浏览器到 Controller 经历什么？
- PHP-FPM 进程数怎么估算？
- OPcache 开启后代码为什么有时不立即生效？
- Laravel Octane 和传统 PHP-FPM 有什么区别？

### 3.3 Composer 与 PHP 工程化

学习目标：

- 理解 `composer.json`、`composer.lock`、PSR-4、语义化版本、scripts、包发现。
- 能说明 `require` 和 `require-dev`、`^` 和 `~` 的区别。
- 能设计内部 Composer 包或模块化包结构。

项目实例方向：

- 把通用指标过滤器、审计组件或导入组件设计成可抽离包。
- 使用 Composer scripts 固化测试、格式化、静态分析命令。
- 文档化 Laravel Package Service Provider 自动发现机制。

面试问题：

- 生产环境为什么要提交 `composer.lock`？
- Laravel 如何发现第三方包的 ServiceProvider？
- 自己写 Composer 包需要哪些文件？

### 3.4 Laravel 核心源码

学习目标：

- 掌握 Laravel 13 的 `public/index.php`、`bootstrap/app.php`、Application、Container、Routing、Middleware、Controller、Response。
- 理解 Service Container、Service Provider、Facade、Pipeline、Event、Queue、Validation、Eloquent、Artisan。
- 能把 Laravel 10 旧骨架和 Laravel 13 新骨架差异说清楚。

项目实例方向：

- 为指标平台写独立 ServiceProvider 注册权限、审计和导入服务。
- 用 Middleware 实现登录态、权限、Trace ID、接口签名。
- 用 Facade 或 Contract 封装指标查询引擎。

面试问题：

- ServiceProvider 的 `register()` 和 `boot()` 区别？
- Facade 是静态方法吗？底层如何实现？
- Middleware 洋葱模型是什么？
- Laravel 依赖注入如何解析构造函数参数？

### 3.5 Eloquent 与 MySQL

学习目标：

- 掌握 Migration、Seeder、Factory、Model、关联、Scope、Accessor/Mutator、Observer、Soft Delete。
- 掌握 Query Builder、事务、分页、N+1、Eager Loading、Chunk、Cursor、LazyCollection。
- 深入 MySQL 索引、Explain、事务隔离、MVCC、锁、死锁、分库分表、读写分离。

项目实例方向：

- 指标库、指标分类、地区、时间、频率维度建模。
- 指标查询支持多条件筛选、排序、分页。
- 10 万级导入和 100 万级分页案例。
- 慢 SQL 记录与 Explain 案例。

面试问题：

- Eloquent 为什么容易产生 N+1？
- `with()` 和 `load()` 区别？
- `chunk()` 和 `cursor()` 区别？
- 为什么 `where function(column)` 会导致索引失效？
- 千万级数据分页怎么设计？

### 3.6 Redis、缓存与分布式问题

学习目标：

- 掌握 String、Hash、List、Set、ZSet、Bitmap、Stream。
- 掌握 Laravel Cache、Redis Session、Redis Queue、Lua、Rate Limit。
- 能处理缓存穿透、击穿、雪崩、热点 Key、大 Key、一致性、分布式锁。
- 理解 RDB、AOF、主从、哨兵、Cluster。

项目实例方向：

- 用户权限缓存。
- 指标详情缓存。
- 首页统计缓存。
- 热门指标排行榜 ZSet。
- 导入任务分布式锁。
- 查询接口限流。

面试问题：

- Laravel Cache 和直接操作 Redis 有什么区别？
- 缓存和数据库如何保持一致？
- 分布式锁如何避免误删？
- Redis 队列消息丢失怎么办？
- 大 Key 如何发现和处理？

### 3.7 队列、异步任务与定时任务

学习目标：

- 掌握 Job、Queue Connection、Redis Queue、Delay Job、Retry、Timeout、Failed Job、Horizon。
- 掌握幂等、任务拆分、补偿、死信思想。
- 掌握 Schedule、单机锁、分布式锁、Worker 平滑重启。

项目实例方向：

- CSV/Excel 指标导入异步化。
- 大文件分片处理。
- 异步生成报表。
- 异步发送通知。
- 定时统计访问量。
- 定时清理过期日志。

面试问题：

- Laravel 队列失败后怎么处理？
- Job 为什么要做幂等？
- Worker 更新代码后为什么要 restart？
- 定时任务多机器部署如何避免重复执行？
- Redis Queue 和 RabbitMQ/Kafka 的差异？

### 3.8 HTTP、API 与安全

学习目标：

- 掌握 HTTP 方法、状态码、Header、Cookie、Session、CORS。
- 掌握 CSRF、XSS、SQL 注入、文件上传、反序列化、SSRF、越权、接口签名、重放攻击。
- 掌握 JWT、Sanctum、Passport、OAuth2、HTTPS/TLS、SameSite Cookie。

项目实例方向：

- 登录接口限流。
- CSRF 防护。
- 防水平越权测试。
- 敏感接口二次校验。
- 接口签名和反重放。
- 导出接口权限控制。
- 操作日志和审计日志。

面试问题：

- 认证和授权的区别？
- Laravel 如何防 SQL 注入？
- XSS 如何按输出上下文防护？
- SSRF 在 PHP 项目中如何出现？
- 支付/导出类接口如何防重放？

### 3.9 架构设计与设计模式

学习目标：

- 掌握 MVC、Service、Repository、DTO、Value Object、Factory、Strategy、Pipeline、Observer、Event-Driven、Command、Adapter、Decorator、DI、Domain Service、Application Service。
- 能设计分层架构、模块化架构和可扩展插件点。

项目分层建议：

```text
Controller
  -> FormRequest
  -> Application Service
  -> Domain Service
  -> Repository / Query Object
  -> Model / DB
  -> Resource Response
```

项目实例方向：

- 指标查询使用 Query Object / Specification。
- 导入模块使用 Strategy 支持 CSV、Excel、API。
- 审计日志使用 Event + Listener。
- 权限校验使用 Policy + Middleware。

面试问题：

- Service 层应该放什么？
- Repository 模式是否一定需要？
- 贫血模型和充血模型有什么区别？
- 如何设计可扩展的数据筛选器？

### 3.10 性能优化

学习目标：

- 掌握 PHP-FPM、OPcache、Composer autoload、config cache、route cache、event cache。
- 掌握 SQL 优化、Redis 缓存、队列异步、批量写入、分页优化、文件上传、大数据导出。
- 掌握 Nginx gzip/brotli、CDN、日志性能、慢接口定位、APM、压测、容量评估。

项目实例方向：

- 10 万条指标数据导入。
- 100 万条指标查询分页。
- 首页统计缓存。
- 导出任务异步化。
- wrk/JMeter 压测接口。
- 慢 SQL 和慢接口记录。

面试问题：

- Laravel 接口 2 秒怎么排查？
- PHP-FPM 进程数怎么估算？
- Laravel 为什么生产环境要缓存配置？
- 大数据导出如何避免内存爆掉？

### 3.11 测试、质量与 CI/CD

学习目标：

- 掌握 PHPUnit 12、Pest 4、Feature Test、Unit Test、Mock、数据库测试、HTTP 测试、队列测试。
- 掌握 PHPStan/Psalm、Laravel Pint、GitHub Actions/GitLab CI、Docker build。
- 掌握环境变量管理、灰度发布、回滚、日志监控。

项目实例方向：

- RBAC 权限 Feature Test。
- 指标查询 API Feature Test。
- 导入 Job 幂等 Unit Test。
- 队列、邮件、事件 fake 测试。
- CI 中运行 test、pint、phpstan。

面试问题：

- Unit Test 和 Feature Test 的边界？
- Laravel Queue / Event / Mail 如何测试？
- 旧项目如何逐步补测试？
- CI 如何避免把坏代码发布到生产？

### 3.12 部署与生产环境

学习目标：

- 掌握 Nginx、PHP-FPM、Supervisor、Cron、Docker Compose、`.env`、文件权限、日志切割、数据库备份、发布、回滚、健康检查。
- 掌握 502/504、慢请求、队列堆积、磁盘写满、Redis 热 Key、MySQL 慢查询等排障。

目标部署架构：

```text
Nginx
  -> PHP-FPM
  -> Laravel App
  -> MySQL
  -> Redis
  -> Queue Worker
  -> Scheduler
  -> Storage / MinIO
  -> Log / Monitoring
```

项目实例方向：

- Docker Compose 本地开发环境。
- Nginx + PHP-FPM 部署文档。
- Supervisor 管理 queue worker。
- Cron 或 schedule worker 管理定时任务。
- 健康检查和日志采集。

面试问题：

- Laravel 队列线上如何常驻？
- Scheduler 多机部署如何避免重复执行？
- PHP-FPM 502 怎么排查？
- Nginx 504 和 PHP 超时有什么关系？
- Laravel 生产环境发布步骤是什么？

## 4. 四阶段路线

### 第一阶段：2 周，搭项目骨架

目标：能跑起来，结构清晰，后续所有学习示例有统一入口。

计划产物：

- Laravel 13 + PHP 8.4 环境说明。
- Docker Compose：Nginx、PHP-FPM、MySQL、Redis。
- Inertia 后台基础布局规划。
- 登录注册、RBAC、菜单权限设计文档。
- 基础 CRUD、API Resource、统一响应、统一异常、日志规范规划。
- Laravel 启动流程、Container、Provider、Middleware、Route、Controller、Request、Response 学习笔记。

### 第二阶段：3 周，做业务复杂度

目标：让项目像真实企业系统。

计划产物：

- 指标库管理、指标分类、地区/时间/频率维度。
- 动态筛选、多条件查询、分页排序。
- Excel/CSV 导入、异步导入任务、失败记录。
- 操作日志和审计日志。
- Eloquent、Query Builder、事务、索引、Explain、N+1、chunk/cursor、Job/Queue、Event/Listener、Observer 学习笔记。

### 第三阶段：3 周，做性能和安全

目标：从“能用”提升到“能上线”。

计划产物：

- 权限缓存、首页统计缓存、接口限流。
- 防重复提交、CSRF、XSS、SQL 注入、文件上传安全。
- 大数据导出异步化、慢 SQL 日志、压测。
- PHP-FPM、OPcache、Nginx、HTTP 安全、接口签名、重放攻击、性能排查学习笔记。

### 第四阶段：2-4 周，源码和面试包装

目标：形成资深 PHP 面试表达。

计划产物：

- Laravel 启动源码、Container、Router、Middleware Pipeline、Eloquent 查询构造、Queue Worker 执行流程阅读笔记。
- 项目架构图。
- 性能优化案例。
- 安全加固案例。
- 面试题库和项目包装话术。

最终项目表达：

```text
我基于 Laravel 13 做了一个企业级指标分析平台。
项目包括 RBAC 权限、指标查询、异步导入、缓存优化、队列任务、接口限流、操作审计和 Docker 部署。
学习过程中我重点研究了 Laravel 启动流程、服务容器、Middleware Pipeline、Eloquent 查询机制、Queue Worker、PHP-FPM 和 OPcache 调优。
```

## 5. 后续代码实现原则

- 每个主题必须有可运行入口：HTTP 路由、Artisan 命令、Feature Test 或 Unit Test。
- 每个主题必须有中文说明、面试问答、资深追问和生产风险。
- 业务代码命名使用英文，文档使用中文。
- 示例代码优先放在清晰模块边界内，避免污染 Laravel 默认骨架。
- 不为了模式而模式；Repository、DTO、Domain Service 等只在复杂度确实需要时引入。
- PHP 8.5 新特性可作为专题演示，但主运行环境保持 PHP 8.4。
- 每次新增 API 都必须同步更新 OpenAPI 文档。
- 每次新增后台页面都必须同步更新访问路径说明。
- 每个阶段按 `docs/implementation-execution-plan.md` 的验收标准完成后，才进入下一阶段。
