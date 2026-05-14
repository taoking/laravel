# Redis 缓存可靠性专题

本文对应待开发任务 `P1-01 Redis 缓存专题实验`，目标是把项目里的 Redis 使用从普通缓存提升到能支撑资深面试追问的可靠性案例。

## 1. 代码入口

| 类型 | 路径 | 用途 |
| --- | --- | --- |
| 指标详情缓存 | `app/Domains/Metrics/Services/MetricCacheService.php` | 空值缓存、随机 TTL、锁防击穿、token lock 释放 |
| 热点指标排行 | `app/Domains/Metrics/Services/HotMetricService.php` | Redis ZSet 记录热度，测试环境降级为 Cache |
| Lua 限流实验 | `app/Domains/Metrics/Services/RedisRateLimiterService.php` | Redis Lua 原子限流，Redis 不可用时降级为 Cache |
| Artisan 命令 | `app/Console/Commands/RedisCacheLabCommand.php` | `redis:cache-lab lua-rate-limit` |
| API 入口 | `GET /api/v1/metrics/{id}` | 指标详情缓存和热点指标记录 |
| 测试 | `tests/Feature/PhaseEightRedisCacheReliabilityTest.php` | P1-01 验收测试 |

## 2. 当前缓存策略

指标详情缓存：

- Key：`metrics:detail:{id}`
- 正常值 TTL：`600 + random_int(0, 120)` 秒。
- 空值 TTL：60 秒。
- 重建锁：`metrics:detail:{id}:rebuild-lock`。
- 写入、更新、删除指标时清理详情缓存。

热点指标：

- Redis Key：`metrics:hot`
- Redis 数据结构：ZSet。
- 写入：`ZINCRBY metrics:hot 1 {metric_code}`。
- 查询：`ZREVRANGE metrics:hot 0 {limit - 1} WITHSCORES`。
- Redis 不可用时，测试环境降级为 Cache 数组。
- 热点 Key TTL：`3600 + random_int(0, 300)` 秒。

Lua 限流：

- 命令：`php artisan redis:cache-lab lua-rate-limit --key=metric-query-demo --limit=3 --decay=60`
- Redis 可用时使用 Lua 脚本保证 `INCR + EXPIRE + 判断阈值` 原子执行。
- Redis 不可用时先降级为 Cache bucket；如果当前 Cache store 也不可用，再降级为进程内 memory bucket，保证本地测试和离线演示可运行。

## 3. 缓存三大问题

### 3.1 缓存穿透

问题：请求不存在的指标 ID 时，如果每次都查数据库，会让恶意请求绕过缓存打到数据库。

项目处理：

- `MetricCacheService::detailById()` 对不存在的指标写入空值 payload。
- 空值不是 `null`，而是内部 sentinel 数组，因此 `Cache::has()` 可以识别缓存存在。
- 空值 TTL 设置为 60 秒，避免长期缓存误伤后续新建数据。

面试表达：

> 对不存在数据不能简单缓存 null，因为很多缓存 API 会把 null 当成未命中。我在项目里用内部 sentinel payload 表示空值，并设置较短 TTL，既能挡住穿透，又不会长期污染缓存。

### 3.2 缓存击穿

问题：热点指标缓存失效时，大量请求同时查询数据库并重建同一个缓存。

项目处理：

- 重建详情缓存前先获取 `Cache::lock()`。
- 拿到锁的请求负责查库和写缓存。
- 没拿到锁时最多等待 1 秒；超时后降级为直接查询，避免接口长时间阻塞。
- 提供 `acquireRebuildLock()` 和 `releaseRebuildLock()` 演示 token lock，避免误删别人的锁。

面试表达：

> 分布式锁释放必须校验 owner token。否则进程 A 的锁过期后，进程 B 获得新锁，进程 A 再释放就可能误删进程 B 的锁。

### 3.3 缓存雪崩

问题：大量 Key 使用同一个 TTL，集中失效时会造成数据库流量尖峰。

项目处理：

- 指标详情 TTL 增加 0 到 120 秒随机抖动。
- 热点指标 TTL 增加 0 到 300 秒随机抖动。
- 重要缓存可继续结合预热、分批刷新和熔断降级。

面试表达：

> 随机 TTL 不是解决雪崩的唯一手段，但它能低成本打散失效时间。核心缓存还要结合预热、限流、降级和监控。

## 4. Laravel Cache 与 Redis 原生命令边界

| 场景 | 推荐方式 | 原因 |
| --- | --- | --- |
| 普通键值缓存 | Laravel Cache | 便于切换驱动，测试环境可用 array store |
| 分布式锁 | Laravel Cache Lock | 封装 owner token 和 restoreLock |
| 热门排行榜 | Redis ZSet | 需要有序分值和 TopN |
| 原子复合操作 | Redis Lua | 多条命令需要原子性 |
| Session/Queue | Laravel 配置驱动 | 框架已有生命周期管理 |
| 大 Key 扫描 | Redis 原生命令或运维工具 | 需要观测 key size、encoding、memory usage |

本项目原则：

- 业务代码默认优先使用 `Cache` 抽象，保证测试和本地演示稳定。
- 只有 ZSet、Lua、运维观测这类 Cache 抽象表达不了的能力，才直接使用 Redis。

## 5. 大 Key 与热 Key

大 Key 风险：

- 单个 value 过大导致网络传输慢。
- 删除大 Key 可能阻塞 Redis。
- 复制、AOF、RDB 都会受影响。

热 Key 风险：

- 单个 Key 请求量过高，导致 Redis 单线程压力集中。
- 热点指标详情、首页统计、排行榜都可能成为热 Key。

项目处理思路：

- 指标详情按 ID 拆 Key，不把所有详情塞进一个大 Hash。
- 排行榜只保存 score，不保存完整指标详情。
- 热点详情使用锁和随机 TTL。
- 生产上应补 `MEMORY USAGE`、`SCAN`、慢日志和客户端侧指标。

## 6. 缓存一致性

当前项目采用 Cache Aside：

1. 读请求先查缓存。
2. 缓存未命中再查数据库。
3. 查到后写缓存。
4. 更新或删除指标后删除缓存。

优点：

- 实现简单，适合读多写少。
- 写路径不用同步更新复杂缓存结构。

风险：

- 删除缓存后瞬时读请求可能重建旧数据。
- 高并发更新时可能出现短暂不一致。
- 需要结合消息事件、延迟双删或版本号进一步增强。

当前项目还通过 Kafka `metric.data.changed` 事件提供缓存刷新案例，见 `docs/queue/kafka-practice.md`。

## 7. 验收命令

```bash
php artisan list redis --raw
php artisan redis:cache-lab lua-rate-limit --key=metric-query-demo --limit=3 --decay=60
php artisan test --filter=PhaseEightRedisCacheReliabilityTest
composer analyse
php artisan test
./vendor/bin/pint --test
```

## 8. 面试问题

基础问题：

1. Laravel Cache 和 Redis 原生命令有什么区别？
2. 什么是缓存穿透、击穿、雪崩？
3. 为什么空值缓存不能直接存 `null`？
4. 为什么热点排行榜适合 ZSet？
5. 为什么更新指标后要删除缓存？

资深追问：

1. 分布式锁为什么要 owner token？
2. Lua 限流相比普通 Redis 命令有什么优势？
3. 缓存和数据库如何保证一致性？
4. 大 Key 如何发现和处理？
5. 热 Key 如何发现和治理？
6. Cache Aside 在高并发写入下有什么风险？
7. Redis 不可用时接口应该失败、降级还是绕过缓存？

## 9. 当前边界

- 本专题为了自动化测试稳定，Redis 不可用时会降级到 Cache；Cache store 也不可用时，Lua 限流实验会继续降级为进程内 memory bucket。
- 当前还没有接入 Redis Cluster、Sentinel、`MEMORY USAGE` 或真实线上监控。
- 当前 Lua 限流是学习实验入口，生产接口仍使用 Laravel RateLimiter。
- 后续如果要继续增强，可以补 Redis Cluster、热点 Key 监控、延迟双删和缓存版本号。
