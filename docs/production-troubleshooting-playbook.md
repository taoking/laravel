# Production Troubleshooting Playbook

本文档对应长期计划中的性能优化、线上故障复盘、Linux/网络和资深面试场景题主线。目标是把常见线上问题沉淀成“现象、首轮检查、可能根因、止血修复、预防措施、面试表达”。

## 学习目标

- 能按影响面、链路耗时、基础设施状态和最近变更系统排查故障。
- 能区分止血、根因定位、修复和预防，而不是只说“重启服务”。
- 能把 Laravel、PHP-FPM、MySQL、Redis、MQ、Nginx、Docker 和 Linux 命令串成排查路径。
- 能在面试中清楚表达慢接口、消息堆积、Redis 热 key、502/504、CPU 飙高等场景。

## 源码入口

- Playbook 数据：`app/Learning/LaravelInterview/Support/ProductionTroubleshootingPlaybook.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewTroubleshootCommand.php`
- 命令注册：`app/Learning/LaravelInterview/Providers/InterviewExampleServiceProvider.php`
- 测试：`tests/Feature/InterviewTroubleshootCommandTest.php`

## 运行方式

列出所有场景：

```bash
docker compose exec laravel.test php artisan interview:troubleshoot
```

查看单个场景：

```bash
docker compose exec laravel.test php artisan interview:troubleshoot mysql-slow-query
```

输出 JSON：

```bash
docker compose exec laravel.test php artisan interview:troubleshoot mq-backlog --json
```

## 当前覆盖场景

- `slow-api`：接口突然变慢。
- `mysql-slow-query`：MySQL 慢查询、执行计划、索引、锁等待。
- `redis-hot-key`：Redis 热 key、大 key、缓存击穿。
- `mq-backlog`：RabbitMQ 或业务 MQ 消息堆积。
- `queue-worker-memory`：Laravel Queue Worker 内存增长和常驻进程风险。
- `http-502-504`：Nginx、PHP-FPM、上游超时和网关错误。
- `cpu-spike`：PHP/MySQL/Redis/系统 CPU 飙高。
- `disk-full`：磁盘容量或 inode 写满。
- `retry-storm`：超时重试放大故障。

## 面试回答框架

### 1. 先判断影响面

先确认是单接口、单租户、单节点、单机房还是全站问题。影响面决定是否先降级、限流、回滚或扩容。

### 2. 再拆链路耗时

Laravel 接口通常拆成 Nginx、PHP-FPM、Controller/Service、SQL、Redis、HTTP Client、队列派发和序列化输出。没有链路耗时，就容易靠猜。

### 3. 保留现场后止血

CPU、内存、磁盘、MQ 堆积这类问题不要只重启。重启前尽量保留日志、慢查询、进程信息和关键输入样本；但当故障影响核心链路时，止血优先。

### 4. 修复后补预防

复盘必须落到监控、告警、压测、代码约束、发布检查、容量评估和文档更新。只修一次 bug 不算闭环。

## 高频追问

### 慢接口如何判断瓶颈在 PHP、MySQL、Redis 还是网络？

用 trace id 和分层耗时判断。PHP 层看 FPM worker、应用日志和 CPU；MySQL 看慢查询、EXPLAIN、锁等待和连接数；Redis 看延迟、slowlog、CPU、命中率和 key 分布；网络和下游看 HTTP client timeout、重试和上游耗时。

### 消息堆积百万级如何处理？

先判断生产和消费速率差，暂停非核心生产者或限流入口；确认消费者失败率、下游瓶颈、毒消息和幂等；再扩容消费者、拆队列、转移死信、批量补偿。没有幂等前不要盲目并发重放。

### 502 和 504 有什么区别？

502 更偏向网关连接上游失败，例如 PHP-FPM 挂掉、socket 错误、upstream 连接失败。504 是网关等上游响应超时，常见原因是慢 SQL、外部接口卡住、FPM worker 打满或超时预算不合理。

### 重试为什么可能放大故障？

多层重试会形成乘法放大。一次下游慢响应可能触发 HTTP client retry、队列 retry、网关 retry 和用户刷新。重试必须有超时、最大次数、指数退避、jitter、熔断和幂等。

## 常用命令提示

```bash
docker compose ps
docker compose logs -f laravel.test
docker compose exec laravel.test php artisan interview:infra-check
docker compose exec laravel.test php artisan queue:failed
docker compose exec laravel.test tail -n 100 storage/logs/laravel.log
```

生产 Linux 排查常用命令：

```bash
top
ps aux --sort=-%cpu | head
df -h
df -i
du -sh *
ss -lntp
lsof -i :9000
tail -f /var/log/nginx/error.log
```

## 生产实践提示

- 每个核心接口都应该有 trace id 和分层耗时。
- SQL、Redis、MQ、HTTP Client 都要有独立超时和指标。
- 队列任务必须幂等，失败要进死信或失败任务表。
- FPM worker、queue worker、Octane worker 都要有生命周期和内存限制。
- 故障复盘要写清楚：影响、时间线、根因、修复、预防、责任人和验证方式。
