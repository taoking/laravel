# PHP-FPM、Worker、Scheduler 与 Octane 运行机制

本文对应 `P1-05 多进程、Worker 与 Octane 专题`，用于补齐 PHP 运行机制、常驻进程和生产排障面试表达。

## 1. 代码入口

- 实验命令：`app/Console/Commands/RuntimeWorkerLabCommand.php`
- 命令：`php artisan runtime:worker-lab memory-growth`
- 命令：`php artisan runtime:worker-lab lifecycle`
- 队列 Job：`app/Jobs/ProcessMetricImportJob.php`
- Supervisor：`docker/supervisor/worker.conf`
- 部署文档：`docs/deploy/docker-deploy-runbook.md`
- 测试：`tests/Feature/PhaseTenRuntimeProcessTest.php`

## 2. 生命周期对比

```text
PHP-FPM
  Nginx -> FPM worker -> public/index.php -> Laravel -> Response -> 请求内存释放

CLI Command
  shell -> artisan -> Laravel -> Command handle() -> 进程退出

Queue Worker
  Supervisor -> php artisan queue:work -> Laravel 常驻 -> 循环取 Job -> 需要重启

Scheduler
  cron schedule:run 短进程
  或 schedule:work 常驻进程

Octane
  Swoole/RoadRunner -> Laravel 常驻内存 -> 多请求复用同一应用实例
```

## 3. 实验命令

内存增长模拟：

```bash
php artisan runtime:worker-lab memory-growth --iterations=5 --chunk-kb=128 --sleep-ms=0
```

生命周期对比：

```bash
php artisan runtime:worker-lab lifecycle
```

这个命令用于解释常驻进程风险：如果队列 Worker 或 Octane 请求处理过程中不断把数据放进长生命周期变量，内存不会像普通 FPM 请求那样自然归零。

## 4. PHP-FPM

特点：

- master/worker 模型。
- 每个请求由一个 worker 处理。
- 请求结束后，绝大多数请求级变量释放。
- OPcache 缓存编译后的 PHP 字节码。

常见配置：

- `pm = static`：固定 worker 数，适合资源稳定场景。
- `pm = dynamic`：按空闲 worker 动态调整。
- `pm = ondemand`：按请求启动 worker，省资源但冷启动更多。

进程数估算：

```text
max_children ≈ 可用内存 / 单个 PHP-FPM worker 峰值内存
```

排障：

- 502：FPM 不可达、崩溃、进程耗尽。
- 504：上游响应超时，可能是 PHP 慢、SQL 慢、外部接口慢。
- 代码不生效：检查 OPcache、部署路径、FPM reload。

## 5. Queue Worker

特点：

- `queue:work` 是长进程。
- 启动时加载代码、配置和容器。
- 执行多个 Job 后仍复用同一个进程。

发布后必须：

```bash
php artisan queue:restart
```

原因：旧 Worker 不会自动加载新代码。`queue:restart` 会写入重启信号，Worker 当前 Job 执行完后退出，再由 Supervisor 拉起新进程。

风险：

- 内存增长。
- 静态变量污染。
- 单例服务保存 Job 状态。
- timeout 与 retry_after 配置不匹配。

## 6. Scheduler 多机部署

`schedule:run` 通常由 cron 每分钟触发。多台机器同时部署时，同一个任务可能重复执行。

处理方式：

- 使用 `withoutOverlapping()` 避免单任务重入。
- 使用 `onOneServer()` 配合共享缓存锁。
- 或只在一台 scheduler 节点运行。

## 7. Octane

Octane 与 FPM 最大区别：Laravel 应用常驻内存。

优点：

- 减少每个请求重复 bootstrap 成本。
- 高并发下吞吐更好。

风险：

- 静态变量和单例服务可能跨请求污染。
- 请求对象、用户态数据不能保存到长生命周期对象中。
- 内存泄漏更明显，需要 max request、reload 和监控。

当前项目未接入 Octane，但源码和服务设计时要避免把请求状态保存在 singleton 中。

## 8. OPcache

OPcache 缓存 PHP 编译结果，提高请求性能。

生产注意：

- 发布后需要确保新代码被 FPM/OPcache 识别。
- 开启 `opcache.validate_timestamps=0` 时，必须 reload FPM 或清理 OPcache。
- CLI 和 FPM 的 OPcache 配置可能不同。

## 9. 面试问题

基础问题：

1. PHP-FPM master/worker 模型是什么？
2. `pm = static / dynamic / ondemand` 有什么区别？
3. Queue Worker 为什么要 `queue:restart`？
4. Scheduler 多机部署为什么会重复执行？
5. Octane 和传统 FPM 有什么区别？

资深追问：

1. PHP-FPM 进程数如何估算？
2. 502 和 504 分别如何排查？
3. Worker 内存泄漏如何发现和缓解？
4. OPcache 开启后代码为什么可能不立即生效？
5. Octane 下 Service Container 和 singleton 有什么风险？

## 10. 验收命令

```bash
php artisan runtime:worker-lab lifecycle
php artisan runtime:worker-lab memory-growth --iterations=5 --chunk-kb=64
php artisan test --filter=PhaseTenRuntimeProcessTest
```
