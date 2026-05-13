# Learning Index

本文档是当前 Laravel 长期学习项目的导航页。完整长期规划见 `docs/senior-php-laravel-learning-plan.md`。

## 当前项目基线

- Laravel 主线：Laravel 10。
- PHP 主运行环境：Docker PHP 8.3。
- 基础设施：MySQL 8、Redis 7、RabbitMQ、Mailpit。
- 学习示例：默认隔离并关闭，通过 `INTERVIEW_EXAMPLES_ENABLED=true` 启用。
- 文档语言：中文。
- 代码命名：英文。

## 推荐阅读顺序

1. `docs/senior-php-laravel-learning-plan.md`：长期学习、复盘和资深面试范围。
2. `docs/project-start-stop.md`：Docker 环境启动、验证、关闭和排错。
3. `docs/docker-interview-examples.md`：Docker Compose、本地服务编排、volume/network/healthcheck 和部署面试点。
4. `docs/laravel-10-framework-study-guide.md`：Laravel 10 骨架、生命周期和高频面试点。
5. `docs/laravel-10-startup-shutdown-flow.md`：启动和关闭流程专项复盘。
6. `docs/laravel-interview-examples.md`：当前 Laravel 组件示例说明。
7. `docs/mysql-interview-examples.md`：MySQL 索引、执行计划、事务锁、幂等和分页优化示例。
8. `docs/php-language-features-examples.md`：PHP 8.1-8.5 新特性、版本门控和升级风险示例。
9. `docs/php-runtime-interview-examples.md`：Composer autoload、OPcache、JIT、GC、FPM/CLI 差异示例。
10. `docs/redis-interview-examples.md`：Redis 数据结构、锁、Lua、Pipeline、限流和 Stream 示例。
11. `docs/laravel-queue-interview-examples.md`：Laravel Queue、Job 重试、唯一任务、失败处理、Schedule 和 Supervisor 示例。
12. `docs/rabbitmq-interview-examples.md`：RabbitMQ exchange、queue、ACK、重试和死信示例。
13. `docs/mq-comparison-interview-examples.md`：RabbitMQ、Kafka、RocketMQ 模型、场景、取舍和可靠性对比。
14. `docs/php-process-interview-examples.md`：PHP-FPM、pcntl、多进程、线程和协程边界示例。
15. `docs/production-troubleshooting-playbook.md`：慢接口、慢 SQL、Redis 热 key、MQ 堆积、502/504、CPU、磁盘和重试风暴复盘。
16. `docs/security-interview-checklist.md`：认证、授权、SQL 注入、XSS、CSRF、SSRF、上传、脱敏、Webhook 和限流安全清单。
17. `docs/system-design-interview-playbook.md`：支付、库存、订单、通知、RBAC、审计日志系统设计题。

## 学习主线

### Laravel 框架核心

- 生命周期、Kernel、ServiceProvider、Container、Facade。
- Route、Controller、Middleware、FormRequest、Resource。
- Eloquent、Query Builder、Migration、Factory、Seeder。
- Event、Listener、Queue、Mail、Notification、Schedule。
- Auth、Gate、Policy、Sanctum。
- Unit Test、Feature Test、Fake、Mock。

### PHP 高级与新特性

- 类型系统、闭包、Generator、Iterator、Trait、Attribute、Reflection。
- Composer 自动加载、OPcache、JIT、GC、PHP-FPM。
- PHP 8.1 到 8.5 新特性专题。
- Swoole、Octane、RoadRunner、协程和常驻内存风险。

### 数据库与中间件

- MySQL 索引、执行计划、事务、锁、MVCC、死锁、读写分离。
- Redis 数据结构、缓存一致性、分布式锁、限流、Stream、Cluster。
- RabbitMQ exchange、queue、routing key、ACK、重试、死信、延迟队列。
- Kafka/RocketMQ 作为高级对比专题。

### 工程与架构

- Docker、docker-compose、镜像、volume、network、healthcheck。
- Linux 进程、文件描述符、日志、权限、systemd、cron、Supervisor。
- HTTP、HTTPS、DNS、反向代理、负载均衡、超时和重试。
- DDD、分层、幂等、最终一致性、限流、降级、熔断、灰度发布。

### 资深面试与线上故障

- 性能优化：慢 SQL、缓存优化、队列削峰、压测、链路分析。
- 安全：认证授权、CSRF、XSS、SQL 注入、SSRF、文件上传。
- 故障复盘：502/504、Redis 热 key、MQ 堆积、死锁、CPU 飙高、磁盘写满。
- 系统设计：支付、订单、库存、消息通知、后台权限、日志审计。

## 学习单元交付标准

每个后续学习单元至少包含：

- 一个明确主题。
- 一个源码或配置入口。
- 一个可运行入口，优先使用 HTTP 路由、Artisan 命令或测试用例。
- 一段中文说明，包含核心概念和生产风险。
- 一组面试问题，包含基础回答和资深追问。
- 一个验证命令或测试方式。

## 当前已存在的学习材料

- Laravel 组件样例：`app/Learning/LaravelInterview`。
- 示例路由：`routes/interview_examples.php`。
- 示例配置：`config/interview_examples.php`。
- 学习摘要命令：`php artisan interview:digest`。
- 基础设施烟测命令：`php artisan interview:infra-check`。
- Docker 专题命令：`php artisan interview:docker`。
- Docker 专题说明：`docs/docker-interview-examples.md`。
- MySQL 专题命令：`php artisan interview:mysql`。
- MySQL 专题说明：`docs/mysql-interview-examples.md`。
- PHP 新特性专题命令：`php artisan interview:php-features`。
- PHP 新特性专题说明：`docs/php-language-features-examples.md`。
- PHP 运行机制专题命令：`php artisan interview:php-runtime`。
- PHP 运行机制专题说明：`docs/php-runtime-interview-examples.md`。
- Redis 专题命令：`php artisan interview:redis`。
- Redis 专题说明：`docs/redis-interview-examples.md`。
- Laravel Queue 专题命令：`php artisan interview:queue`。
- Laravel Queue 专题说明：`docs/laravel-queue-interview-examples.md`。
- RabbitMQ 专题命令：`php artisan interview:rabbitmq`。
- RabbitMQ 专题说明：`docs/rabbitmq-interview-examples.md`。
- MQ 对比专题命令：`php artisan interview:mq-compare`。
- MQ 对比专题说明：`docs/mq-comparison-interview-examples.md`。
- PHP 进程模型命令：`php artisan interview:process`。
- PHP 进程模型说明：`docs/php-process-interview-examples.md`。
- 线上故障复盘命令：`php artisan interview:troubleshoot`。
- 线上故障复盘说明：`docs/production-troubleshooting-playbook.md`。
- 安全面试清单命令：`php artisan interview:security`。
- 安全面试清单说明：`docs/security-interview-checklist.md`。
- 系统设计专题命令：`php artisan interview:system-design`。
- 系统设计专题说明：`docs/system-design-interview-playbook.md`。
- 示例迁移：`database/migrations/2026_04_25_000000_create_interview_example_posts_table.php`、`database/migrations/2026_04_25_000001_create_interview_example_comments_table.php`。
- 示例说明：`docs/laravel-interview-examples.md`。

## 后续执行原则

- 先补环境闭环，再扩展组件专题。
- 每次新增主题时同步更新本文档或专题文档索引。
- 不把学习示例默认接入真实业务入口。
- 不把 PHP 8.4/8.5 专题写成 Laravel 10 主应用的硬依赖。
- 所有资深面试题都要尽量关联可运行示例、生产场景或故障案例。
