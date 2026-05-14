# 导入导出 Worker 与 MQ 可靠性说明

本文记录 CSV 导入、导出任务、Redis Queue 可靠性、补偿命令和 MQ 选型对比。它对应待开发任务 `P1-03 MQ 与队列可靠性专题`，目标是让导入队列可以承受资深面试里关于消息丢失、重复消费、失败补偿和选型的连续追问。

## 1. API 入口

- 创建导入任务：`POST /api/v1/imports`
- 导入任务列表：`GET /api/v1/imports`
- 导入任务详情：`GET /api/v1/imports/{id}`
- 重试导入任务：`POST /api/v1/imports/{id}/retry`
- 创建导出任务：`POST /api/v1/exports`

接口文档入口：

- Swagger UI：`http://127.0.0.1:8000/docs/api`
- OpenAPI YAML：`http://127.0.0.1:8000/docs/openapi.yaml`

## 2. CSV 导入格式

```csv
metric_code,region_code,frequency_code,period_date,period_label,value,source
revenue_amount,CN-SH,monthly,2026-05-01,2026-05,1300000,test
```

必填字段：

- `metric_code`
- `region_code`
- `frequency_code`
- `period_date`
- `period_label`
- `value`

导入 Job 会按 `metric_code`、`region_code`、`frequency_code`、`period_date` 定位指标值，存在则更新，不存在则创建。因此重复执行不会产生重复指标值。

## 3. 状态机

`import_tasks.status` 当前使用以下状态：

| 状态 | 含义 | 是否终态 | 后续动作 |
| --- | --- | --- | --- |
| `pending` | 已创建任务，等待 Worker 执行 | 否 | Job 可以执行 |
| `processing` | Job 正在执行 | 否 | 重复 Job 直接跳过 |
| `completed` | 全部行成功 | 是 | 重复 Job 直接跳过 |
| `completed_with_errors` | 文件处理完成，但存在业务行失败 | 是 | 重复 Job 直接跳过；可人工决定是否重新上传或重试 |
| `failed` | Job 级异常，例如文件不可读、未知异常 | 否 | 可由重试接口或补偿命令重新派发 |

终态幂等规则：

- `completed` 和 `completed_with_errors` 都是终态。
- 如果 Redis Queue 因 ack/delete 失败导致同一个 Job 再次投递，Job 会检查终态并跳过。
- 如果任务还处于 `failed`，表示前一次执行没有完成，可以重新执行。

## 4. 幂等策略

HTTP 创建层：

- 请求头：`Idempotency-Key`
- 同一个幂等键重复提交时，直接返回已有任务。
- 不重复入库、不重复派发 Job。

Job 执行层：

- 任务开始时记录 `attempts`。
- 终态任务跳过，防止重复消费造成重复写入。
- 指标值写入使用业务唯一维度查找后更新或创建，避免重复插入。
- 业务行失败写入 `import_failures`，Job 级异常写入 `import_tasks.error_message` 和 `failure_type`。

Kafka 事件层：

- `ProcessMetricImportJob` 完成后发布 `metric.import.completed`。
- Kafka 消费侧通过 `consumed_messages` 表实现 `consumer_group + idempotency_key` 幂等。
- Kafka 专题见 `docs/queue/kafka-practice.md`。

## 5. 失败分类

`import_tasks` 新增可靠性字段：

| 字段 | 用途 |
| --- | --- |
| `attempts` | 记录 Job 实际开始处理次数 |
| `failure_type` | 记录 Job 级失败分类 |
| `last_failed_at` | 最近一次 Job 级失败时间 |
| `compensated_at` | 最近一次人工补偿时间 |
| `compensation_reason` | 补偿来源，例如接口重试或 CLI 补偿 |

当前失败分类：

| failure_type | 场景 | 处理建议 |
| --- | --- | --- |
| `storage` | 文件不存在、文件不可读、存储临时不可用 | 确认文件仍存在后补偿 |
| `data_format` | 日期或格式解析异常 | 修复文件后重新上传或重试 |
| `unexpected` | 未知异常 | 查看日志、保留现场、必要时人工补偿 |

业务行失败不会让 Job 失败，而是进入 `completed_with_errors`，并写入 `import_failures`。这类失败通常属于数据质量问题，不应该让整个文件反复重试。

## 6. 重试和补偿命令

接口重试：

```bash
POST /api/v1/imports/{id}/retry
```

接口重试会：

- 删除历史行失败记录。
- 重置状态为 `pending`。
- 清空行统计、错误信息和失败分类。
- 记录 `compensated_at` 和 `compensation_reason=manual retry endpoint`。
- 重新派发 `ProcessMetricImportJob`。

CLI 补偿：

```bash
php artisan imports:compensate --dry-run
php artisan imports:compensate --id=10 --clear-failures
php artisan imports:compensate --status=failed --older-than-minutes=5 --limit=20
```

CLI 补偿适用于生产排障：

- `--dry-run`：只列出匹配任务，不修改数据。
- `--id=*`：指定任务 ID。
- `--status=failed`：按状态筛选，默认补偿 failed 任务。
- `--older-than-minutes=5`：只处理失败或更新时间超过 5 分钟的任务，避免抢正在恢复的任务。
- `--limit=20`：限制单次补偿数量。
- `--clear-failures`：重新派发前删除历史行失败记录。

## 7. Worker 命令

本地：

```bash
php artisan queue:work --tries=3 --timeout=120
```

部署后平滑重启 Worker：

```bash
php artisan queue:restart
```

生产 Supervisor 示例：

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

## 8. 定时任务

- 命令：`php artisan metrics:daily-summary`
- 调度：`routes/console.php` 中每日 `01:00` 执行，并启用 `withoutOverlapping()`。

多机部署时，Scheduler 还应使用共享缓存锁或单独的调度节点，避免多台机器重复执行同一个定时任务。

## 9. Redis Queue、RabbitMQ、Kafka 选型

| 维度 | Redis Queue | RabbitMQ | Kafka |
| --- | --- | --- | --- |
| 主要定位 | 后台任务队列 | 业务消息队列 | 事件流和日志流 |
| Laravel 集成 | 原生支持，开发成本低 | 需要扩展包或自定义连接 | 当前项目通过 Kafka 专题模块封装 |
| 顺序性 | 单队列近似顺序，但受重试影响 | 队列内顺序较好，复杂路由可控 | partition 内有序 |
| 消息确认 | Worker 成功后删除 Job | ack/nack/requeue | offset commit |
| 重试 | Laravel Worker tries/backoff | 队列和死信交换机 | 消费端重试、死信 topic、offset 控制 |
| 死信 | Laravel failed_jobs 或业务表 | DLX/DLQ 原生能力强 | dead letter topic |
| 回放 | 不适合大规模回放 | 可以重入队，但不是日志存储 | 天然适合按 offset 回放 |
| 适用场景 | CSV 导入、导出、通知、报表生成 | 订单状态、可靠投递、复杂路由 | 指标变更、审计事件、跨系统事件分发 |

本项目选型：

- CSV 导入、导出任务使用 Redis Queue，因为它是后台任务，不需要跨系统回放。
- `metric.import.completed`、`metric.data.changed`、`audit.event.created` 使用 Kafka，因为它们是业务事实，需要分发、消费幂等、lag 观察和死信重放。
- RabbitMQ 暂不落地代码，作为面试选型对比：当系统需要强路由能力、ack/nack、死信交换机和较传统的业务消息队列语义时，可以优先考虑 RabbitMQ。

## 10. 常见追问

基础问题：

1. Job 为什么必须幂等？
2. `Idempotency-Key` 解决的是 HTTP 重复提交还是 MQ 重复消费？
3. Worker 更新代码后为什么要执行 `queue:restart`？
4. `completed_with_errors` 为什么也要作为终态？
5. 业务行失败和 Job 级失败为什么要分开记录？

资深追问：

1. Redis Queue 任务执行成功但删除 Job 失败怎么办？
   - 可能被再次投递，所以 Job 必须检查任务终态并保证业务写入幂等。
2. Job 执行一半失败如何补偿？
   - Job 级异常写入 `failed`、`failure_type`、`last_failed_at`；人工确认后用重试接口或 `imports:compensate` 重新派发。
3. 为什么不能让所有行失败都触发队列重试？
   - 行失败多是业务数据问题，重试无法修复，只会放大队列压力；应记录 `import_failures` 并让用户修正数据。
4. Redis Queue 和 Kafka 的边界是什么？
   - Redis Queue 处理“要做的任务”，Kafka 分发“已经发生的业务事实”。
5. 如果数据库写入成功但 Kafka 事件发布失败怎么办？
   - 当前项目是学习版，Job 完成后直接发布事件；生产级方案应引入 Outbox Pattern，把业务写入和待发送事件放在同一数据库事务中，再由独立进程可靠投递。

## 11. 验收命令

```bash
php artisan list imports --raw
php artisan imports:compensate --dry-run
php artisan test --filter=PhaseFourImportQueueTest
composer analyse
php artisan test
./vendor/bin/pint --test
```

当前专题测试覆盖：

- 缺文件导致 Job 级失败，记录 `failure_type=storage` 和 `attempts`。
- `completed_with_errors` 终态重复执行跳过。
- HTTP 幂等键防重复创建任务。
- 重试接口重新派发任务。
- `imports:compensate` 可补偿 failed 任务。
- `imports:compensate --dry-run` 不修改任务。
