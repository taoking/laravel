# Laravel Queue Interview Examples

本文档对应长期计划中的 Laravel 队列、事件异步化、Schedule、Supervisor 和常驻 worker 主线，用于复盘 Job 生命周期、重试、失败处理、幂等和生产部署风险。

## 学习目标

- 能说清 Laravel Queue 抽象和 Redis/database/SQS/RabbitMQ 等后端的关系。
- 能解释 `tries`、`backoff`、`timeout`、`retry_after`、`failed_jobs` 的配合关系。
- 能演示唯一任务、任务互斥、失败回调和 worker 重启。
- 能回答为什么部署后要 `queue:restart`，以及为什么任务必须幂等。

## 源码入口

- 示例 Job：`app/Learning/LaravelInterview/Jobs/ProcessInterviewOrderJob.php`
- 示例逻辑：`app/Learning/LaravelInterview/Support/LaravelQueueInterviewExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewQueueCommand.php`
- 队列表迁移：`database/migrations/2026_04_25_000003_create_laravel_queue_tables.php`
- 测试：`tests/Feature/InterviewQueueCommandTest.php`

## 运行方式

```bash
docker compose exec laravel.test php artisan interview:queue
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:queue --json
```

如果要使用 database queue，先确认迁移已执行：

```bash
docker compose exec laravel.test php artisan migrate
```

## 覆盖内容

- `ShouldQueue`：任务进入队列，由 worker 异步消费。
- `ShouldBeUnique`：同一业务 key 防止重复入队。
- `WithoutOverlapping`：同一业务 key 防止并发执行。
- `tries`：最大尝试次数。
- `backoff()`：失败后的退避时间。
- `timeout`：单个任务最大执行时间。
- `failed(Throwable $exception)`：任务最终失败后的补偿或告警入口。
- `queue:restart`：部署后通知常驻 worker 平滑退出并加载新代码。
- `schedule:run`：生产环境通常由 cron 每分钟触发。
- Supervisor：守护 `queue:work` 长进程。

## 面试问答

### Laravel Queue 和 RabbitMQ 是什么关系？

Laravel Queue 是框架抽象，统一了 Job 派发、序列化、重试和 worker 消费模型。Redis、database、SQS、RabbitMQ 是不同后端。RabbitMQ 适合复杂 routing、ACK、死信和跨语言消息场景；Laravel Queue 更贴近应用内异步任务。

### 为什么任务必须幂等？

队列系统通常保证至少一次投递，网络抖动、worker 超时、ACK 失败、重试都可能导致重复执行。支付、库存、发券、发货这类任务必须用业务幂等键、唯一索引和状态机防重复。

### `timeout` 和 `retry_after` 如何设置？

`timeout` 是 worker 允许 Job 执行的最长时间，`retry_after` 是后端认为任务超时后可重新投递的时间。通常 `timeout` 要小于 `retry_after`，否则可能出现一个任务还在跑，另一个 worker 已经重新消费同一任务。

### `ShouldBeUnique` 和 `WithoutOverlapping` 有什么区别？

`ShouldBeUnique` 解决重复入队问题；`WithoutOverlapping` 解决已经入队的任务在执行阶段不能并发处理同一个业务 key。两者常常组合使用，但不能替代数据库层幂等。

### 部署后为什么要 `queue:restart`？

`queue:work` 是常驻进程，不会像 PHP-FPM 请求一样每次重新加载代码。部署后不重启 worker，旧进程可能继续执行旧代码、旧配置或旧依赖。

## 资深追问

- 如何设计高优先级和低优先级队列？
- MQ 堆积时为什么不能只盲目加消费者？
- 如何处理毒消息反复失败？
- 任务里调用第三方接口应该如何设置超时和重试？
- 大数据导出为什么要 chunk/cursor，而不是一次性查询？
- 队列任务失败后如何告警、补偿和人工重放？

## 生产实践提示

- 核心任务必须有幂等键和状态机，不能只依赖队列唯一性。
- 重试要有最大次数、退避、错误分类和告警。
- 高优先级、低优先级、重 CPU、慢第三方接口任务要拆队列。
- worker 必须设置 `--memory`、`--timeout`、`--tries`、`--sleep` 等参数。
- Supervisor 或进程管理器要负责自动重启 worker。
- 部署流程要包含 `php artisan queue:restart`。
