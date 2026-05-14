# Laravel Queue Worker 源码追问

## 项目入口

- `app/Jobs/ProcessMetricImportJob.php`
- `app/Console/Commands/ImportCompensateCommand.php`
- `docs/queue/import-export-worker.md`
- `config/queue.php`
- `docker/supervisor/worker.conf`

## 源码类

- `Illuminate\Queue\Worker`
- `Illuminate\Queue\WorkerOptions`
- `Illuminate\Queue\CallQueuedHandler`
- `Illuminate\Queue\Jobs\Job`
- `Illuminate\Bus\Dispatcher`

## 执行链

创建导入任务：

```text
POST /api/v1/imports
  -> ImportTaskController@store
  -> ProcessMetricImportJob::dispatch($task->id)
  -> Queue backend 保存 job payload
```

Worker 消费：

```text
php artisan queue:work
  -> Illuminate\Queue\Worker
  -> 从 queue backend 取出 Job
  -> 反序列化 job payload
  -> CallQueuedHandler 调用 handle()
  -> 成功则 ack/delete
  -> 失败则按 tries/backoff 释放或进入 failed_jobs
```

## 当前项目可靠性设计

- `ProcessMetricImportJob::$tries = 3`
- `ProcessMetricImportJob::$timeout = 120`
- `import_tasks.attempts` 记录实际开始处理次数。
- `completed` 和 `completed_with_errors` 作为终态，重复投递直接跳过。
- Job 级异常写入 `failure_type`、`last_failed_at`、`error_message`。
- `imports:compensate` 命令支持人工补偿并重新派发。

## Worker 为什么要重启

`queue:work` 是常驻进程。代码部署后，旧 Worker 仍然持有旧代码和旧容器状态，所以需要：

```bash
php artisan queue:restart
```

Laravel 会写入重启信号，Worker 在当前 Job 执行完后退出，再由 Supervisor 拉起新进程。

## 生产风险

- Job 必须幂等，因为可能重复投递。
- timeout 要小于 queue `retry_after`，否则可能同一个任务并发执行。
- 大文件导入要使用流式读取，避免内存爆掉。
- Worker 需要日志、失败告警和补偿入口。
- 代码发布后要重启 Worker。

## 基础问题

1. Laravel Job 是如何被派发和执行的？
2. `tries` 和 `timeout` 分别控制什么？
3. Worker 为什么是常驻进程？
4. Job 失败后会发生什么？
5. 为什么导入 Job 要做幂等？

## 资深追问

1. Job 执行成功但删除队列消息失败怎么办？
2. `retry_after` 小于 Job 最长执行时间会有什么风险？
3. Worker 部署后为什么要 `queue:restart`？
4. 如何设计 Job 补偿命令？
5. Redis Queue、RabbitMQ、Kafka 在可靠性语义上如何取舍？
