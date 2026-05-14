# 项目知识地图目录规划

本文规划后续学习文档目录。它不是一次性要创建的全部文件清单，而是后续每个主题落地时的归档标准。

```text
php-laravel13-learning/
├── 01-php-language/
├── 02-php-runtime/
├── 03-composer/
├── 04-laravel-core/
├── 05-database/
├── 06-redis-cache/
├── 07-queue-schedule/
├── 08-security/
├── 09-architecture/
├── 10-performance/
├── 11-testing-ci/
├── 12-deploy/
└── interview/
```

## 01-php-language

建议文档：

- `类型系统.md`
- `数组与HashTable.md`
- `引用与写时复制.md`
- `OOP与Trait.md`
- `PHP8新特性.md`
- `闭包与Generator.md`
- `错误异常与Throwable.md`

代码实例方向：

- 指标状态 Enum。
- 查询条件 readonly DTO。
- 大文件导入 Generator。
- Trait 冲突示例。
- Attribute 标记审计字段。

面试题方向：

- 数组底层结构。
- Copy-on-Write。
- 引用和对象赋值。
- PHP 7.4 到 8.5 差异。

## 02-php-runtime

建议文档：

- `PHP请求生命周期.md`
- `PHP-FPM工作原理.md`
- `OPcache与JIT.md`
- `内存管理与GC.md`
- `CLI与FPM差异.md`

代码实例方向：

- Runtime 检查 Artisan 命令。
- PHP-FPM 配置说明。
- Worker 内存增长复现实例。
- OPcache/JIT 配置说明。

面试题方向：

- FPM master/worker。
- `pm` 模式选择。
- OPcache 代码不生效。
- Octane 与 FPM 差异。

## 03-composer

建议文档：

- `composer-json-lock.md`
- `PSR4自动加载.md`
- `语义化版本.md`
- `ComposerScripts.md`
- `包开发.md`

代码实例方向：

- Composer scripts 固化测试命令。
- 内部包 ServiceProvider 示例。
- autoload optimize 对比。

面试题方向：

- `composer.lock` 是否提交。
- `^` 和 `~` 区别。
- Laravel 包发现机制。
- 私有 Composer 包结构。

## 04-laravel-core

建议文档：

- `Laravel启动流程.md`
- `服务容器.md`
- `ServiceProvider.md`
- `Facade.md`
- `Middleware管道.md`
- `路由源码.md`
- `Eloquent查询流程.md`
- `QueueWorker流程.md`

代码实例方向：

- 自定义 ServiceProvider。
- Contract + Implementation。
- Middleware Trace ID。
- Facade 或 Manager 封装指标查询。

面试题方向：

- Container 如何解析依赖。
- Provider 注册和启动。
- Facade 静态代理。
- Middleware 洋葱模型。

## 05-database

建议文档：

- `Eloquent.md`
- `QueryBuilder.md`
- `事务.md`
- `索引优化.md`
- `N+1问题.md`
- `Explain与慢SQL.md`
- `大表分页.md`

代码实例方向：

- 指标库表设计。
- 指标维度建模。
- 查询筛选器。
- 事务和锁。
- chunk/cursor 导出。

面试题方向：

- N+1 排查。
- 事务隔离级别。
- MVCC 和锁。
- 分页优化。

## 06-redis-cache

建议文档：

- `Redis数据结构.md`
- `Laravel缓存.md`
- `分布式锁.md`
- `缓存三大问题.md`
- `Redis持久化与集群.md`

代码实例方向：

- 权限缓存。
- 首页统计缓存。
- 热门指标 ZSet。
- 导入任务锁。
- Lua 限流。

面试题方向：

- 缓存一致性。
- 热 Key 和大 Key。
- 锁误删。
- RDB/AOF。

## 07-queue-schedule

建议文档：

- `Laravel队列.md`
- `Job幂等.md`
- `失败重试.md`
- `定时任务.md`
- `Supervisor与Worker.md`
- `RabbitMQKafkaRocketMQ对比.md`

代码实例方向：

- 异步导入 Job。
- 报表生成 Job。
- 失败记录表。
- 定时统计 Command。
- 多机 Schedule 锁。

面试题方向：

- Job 幂等。
- Worker restart。
- 失败任务补偿。
- Redis Queue 和业务 MQ 选型。

## 08-security

建议文档：

- `CSRF.md`
- `XSS.md`
- `SQL注入.md`
- `越权.md`
- `接口签名与重放.md`
- `文件上传安全.md`
- `SSRF.md`

代码实例方向：

- 登录限流。
- 权限 Policy。
- 水平越权测试。
- 接口签名 Middleware。
- 上传文件校验。

面试题方向：

- 认证和授权。
- SQL 注入边界。
- XSS 输出编码。
- SSRF 防护。

## 09-architecture

建议文档：

- `分层架构.md`
- `设计模式.md`
- `DDD简化实践.md`
- `模块化设计.md`
- `指标查询引擎设计.md`

代码实例方向：

- Application Service。
- Domain Service。
- Query Object。
- Strategy 导入器。
- Event-driven 审计。

面试题方向：

- Service 层边界。
- Repository 是否必要。
- 贫血/充血模型。
- 可扩展筛选器。

## 10-performance

建议文档：

- `Laravel性能优化.md`
- `PHP-FPM调优.md`
- `SQL优化案例.md`
- `Redis优化.md`
- `大数据导出.md`
- `压测与容量评估.md`

代码实例方向：

- 慢接口 trace。
- 慢 SQL 日志。
- 缓存命中率统计。
- 异步导出。
- wrk 压测脚本。

面试题方向：

- 2 秒接口排查。
- FPM 进程数估算。
- OPcache。
- 大数据内存控制。

## 11-testing-ci

建议文档：

- `PHPUnit与Pest.md`
- `FeatureTest.md`
- `QueueFake.md`
- `PHPStan.md`
- `Laravel-Pint.md`
- `GitHub-Actions.md`

代码实例方向：

- RBAC Feature Test。
- 指标查询 API Test。
- 导入 Job Unit Test。
- Queue/Event/Mail fake。
- CI workflow。

面试题方向：

- 测试边界。
- Mock/Fake。
- 遗留系统补测试。
- CI 发布门禁。

## 12-deploy

建议文档：

- `Docker部署.md`
- `Nginx配置.md`
- `PHP-FPM配置.md`
- `Supervisor.md`
- `Cron与Scheduler.md`
- `线上排障.md`

代码实例方向：

- Docker Compose。
- Nginx + FPM 配置。
- Supervisor worker 配置。
- health check。
- 发布和回滚 runbook。

面试题方向：

- 502/504 排查。
- Scheduler 多机重复。
- 队列常驻。
- 日志切割和磁盘满。

## interview

建议文档：

- `PHP资深面试题.md`
- `Laravel源码面试题.md`
- `MySQL面试题.md`
- `Redis面试题.md`
- `项目包装.md`
- `系统设计题.md`

输出目标：

- 每个问题给出基础回答、资深追问、项目案例和生产风险。
- 面试表达围绕“Laravel 13 企业级指标分析平台”组织，而不是散点背题。
- 每个答案尽量关联代码实例、测试、命令、压测或排障案例。
