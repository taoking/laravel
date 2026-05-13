# PHP Process Interview Examples

本文档对应长期计划中的并发、进程、线程与协程主线，用于复盘 PHP-FPM、CLI 常驻进程、pcntl、多进程隔离、线程边界和 Octane/Swoole 风险。

## 学习目标

- 能说清 PHP-FPM master/worker 模型。
- 能演示 PHP CLI 下 `pcntl_fork` 的多进程行为。
- 能区分进程、线程、协程在 PHP/Laravel 场景中的适用边界。
- 能解释队列 worker、Schedule、Octane 等常驻进程的风险。

## 源码入口

- 示例逻辑：`app/Learning/LaravelInterview/Support/PhpProcessInterviewExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewProcessCommand.php`
- 命令注册：`app/Learning/LaravelInterview/Providers/InterviewExampleServiceProvider.php`
- 测试：`tests/Feature/InterviewProcessCommandTest.php`

## 运行方式

```bash
docker compose exec laravel.test php artisan interview:process
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:process --json
```

## 覆盖内容

- PHP 版本、SAPI、父进程 PID。
- `pcntl_fork` 创建子进程。
- 子进程退出码。
- 父子进程内存隔离。
- PHP-FPM 多进程 worker 模型。
- 常驻进程与普通 FPM 请求的区别。
- Octane/Swoole/RoadRunner 请求态污染风险。

## 面试问答

### PHP-FPM 的 master/worker 模型是什么？

PHP-FPM master 进程负责管理 worker，worker 负责处理请求。Nginx 把 PHP 请求转给 FPM，FPM worker 执行业务代码并返回响应。worker 数量决定并发处理能力，但过多会导致内存和 CPU 压力。

### PHP 为什么常说是无共享请求模型？

普通 FPM 请求结束后，请求内变量会释放，下一次请求由 worker 重新处理上下文。进程之间默认不共享内存，因此状态共享通常依赖 Redis、数据库、文件、MQ 或 socket。

### 多进程和多线程有什么区别？

进程有独立地址空间，隔离性好但创建和通信成本更高；线程共享进程内存，通信方便但并发安全复杂。Laravel/FPM 生产模型通常不是多线程共享内存模型。

### 协程和线程有什么区别？

协程通常是用户态调度，适合 IO 并发；线程由操作系统调度，可以并行执行但共享内存风险更高。Swoole 协程能提高 IO 并发，但不能自动解决 CPU 密集任务。

### Octane 为什么要特别小心单例和静态变量？

Octane/Swoole/RoadRunner 是常驻内存模型，应用不会每个请求完整重启。请求态数据如果放进 singleton、静态属性或全局变量，可能污染后续请求。

## 资深追问

- PHP-FPM worker 数量如何估算？
- 队列 worker 为什么需要 `--max-jobs`、`--max-time` 或内存限制？
- 部署后为什么要重启 queue worker？
- 常驻进程如何处理信号和平滑退出？
- CPU 密集任务应该放在协程里吗？
- Octane 项目如何发现请求态内存泄漏？

## 生产实践提示

- FPM worker 数量要结合单请求内存、CPU 核数、平均耗时和下游容量评估。
- 队列任务必须幂等，worker 要配置超时、重试、失败告警和重启策略。
- 常驻进程不要把 Request、User、Tenant 等请求态对象放进单例。
- 协程适合 IO 并发，不适合直接解决重 CPU 计算。
- 多进程任务要考虑任务切分、退出码、信号、孤儿进程和资源清理。
