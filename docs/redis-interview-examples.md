# Redis Interview Examples

本文档对应长期计划中的 Redis 主线，用于复盘数据结构、缓存一致性、分布式锁、Lua、Pipeline、限流和 Stream。

## 学习目标

- 能说清 Redis 常见数据结构适合的业务场景。
- 能用 Laravel 运行 Redis 示例，而不是只背概念。
- 能解释 Pipeline、Lua、分布式锁和限流的生产边界。
- 能把 Redis 和 MySQL、MQ、队列幂等一起放进系统设计回答中。

## 源码入口

- 示例逻辑：`app/Learning/LaravelInterview/Support/RedisInterviewExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewRedisCommand.php`
- 命令注册：`app/Learning/LaravelInterview/Providers/InterviewExampleServiceProvider.php`
- 测试：`tests/Feature/InterviewRedisCommandTest.php`

## 运行方式

启动 Docker 环境：

```bash
docker compose up -d
```

执行 Redis 示例：

```bash
docker compose exec laravel.test php artisan interview:redis
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:redis --key=review --json
```

验证基础设施：

```bash
docker compose exec laravel.test php artisan interview:infra-check
```

## 覆盖内容

- String + TTL：订单状态缓存。
- Hash：对象字段缓存。
- List：简单状态流水。
- Set：标签去重。
- Sorted Set：面试主题优先级、排行榜、延迟任务思路。
- Stream：事件日志和消费组的基础入口。
- Pipeline：减少网络往返，但不保证事务原子性。
- Lua：封装原子读改写逻辑。
- Redis Lock：演示 Laravel Cache lock 的基本用法。
- Sliding Window：用 Sorted Set 实现滑动窗口限流。

## 面试问答

### Redis 有哪些常用数据结构？

String 适合缓存标量、JSON、计数器；Hash 适合对象字段；List 适合简单队列或时间线；Set 适合去重、标签、共同好友；Sorted Set 适合排行榜、延迟任务；Stream 适合事件流和消费组。

### Pipeline 和事务有什么区别？

Pipeline 只是批量发送命令，减少网络 RTT，不保证多个命令原子执行。Redis 事务通过 `MULTI/EXEC` 保证命令按顺序连续执行，但不支持传统数据库事务那种自动回滚。

### Lua 为什么常用于 Redis 原子操作？

Lua 脚本在 Redis 单线程执行模型中整体执行，中间不会被其他命令插入，适合限流、库存扣减、锁释放校验等读改写逻辑。风险是脚本不能执行太久，否则会阻塞 Redis。

### Redis 分布式锁有哪些坑？

必须设置过期时间，释放锁时要校验 owner，业务逻辑要幂等。锁超时后业务仍在执行会导致并发穿透；锁只能降低并发冲突概率，不能替代数据库唯一约束、事务和状态机。

### 如何防止缓存穿透、击穿、雪崩？

穿透可用参数校验、空值缓存、布隆过滤器；击穿可用互斥锁、singleflight、热点预热；雪崩可用随机 TTL、多级缓存、限流降级和后台刷新。

### Redis Stream 和 MQ 有什么区别？

Stream 支持消息追加、消费组和 ACK，适合轻量事件流。专业 MQ 在路由、延迟、死信、堆积治理、跨语言生态、运维能力上更完整。生产选型要看可靠性、吞吐、延迟和团队运维能力。

## 资深追问

- 热 key 如何发现和拆分？
- 大 key 会导致哪些阻塞问题？
- 删除缓存失败怎么办？
- Redis 主从切换时锁和缓存会有什么风险？
- 限流应该放在 Nginx、网关、Laravel 还是 Redis？
- Redis Cluster 为什么有 hash slot？

## 生产实践提示

- 所有缓存 key 必须有命名空间和 TTL 策略。
- 大 key、热 key、慢命令需要监控。
- Redis 锁要有 owner token、过期时间和幂等兜底。
- 限流逻辑要考虑时间窗口、突刺流量和误杀。
- Redis 不应该保存唯一事实，关键状态仍应落 MySQL 或可靠存储。
