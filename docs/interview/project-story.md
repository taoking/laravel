# 面试项目包装

## 一句话介绍

我基于 Laravel 13 做了一个多数据源指标分析与内容管理平台，用一个项目串起 PHP 8.4、Laravel 源码机制、MySQL、Redis、队列、缓存、安全、性能优化和 Docker 部署。

## 当前项目能力

- Inertia 后台：登录、后台首页、用户/角色/菜单/指标/导入页面入口。
- Auth/RBAC：用户、角色、权限、菜单树、按钮/API 权限、权限缓存。
- 指标库：分类、地区、频率、指标主表、指标值、筛选排序分页。
- 队列：CSV 导入、失败记录、幂等键、重试、导出任务入口、定时统计命令。
- 安全：限流、接口签名、反重放、非法上传拒绝、审计日志。
- 性能：指标详情缓存、Query Object、排序白名单、Explain 文档、wrk 压测脚本。
- 部署：Nginx、PHP-FPM、MySQL、Redis、Queue Worker、Scheduler、Supervisor。

## 可以讲的源码点

- `bootstrap/app.php` 中路由、中间件和异常处理的集中配置。
- Service Container 如何解析 Controller 和依赖。
- Middleware Pipeline 的执行顺序。
- Facade 与容器服务的关系。
- Eloquent 关系、Eager Loading 和 N+1 风险。
- Queue Job 的幂等、重试和失败补偿。
- RateLimiter、Cache、Event/Listener 的工程化用法。

## 资深追问准备

- 为什么 API 排序字段必须白名单？
- 为什么导入 Job 必须幂等？
- `updateOrCreate` 在 date cast 和唯一索引下可能有什么坑？
- 权限缓存什么时候失效？
- 为什么审计日志不能信任前端传入用户？
- Redis 队列和 MQ 的可靠性差异在哪里？
- FPM 进程数怎么估算？
- OPcache 开启后发布要注意什么？
