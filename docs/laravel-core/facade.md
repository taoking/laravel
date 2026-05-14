# Laravel Facade 源码追问

## 项目入口

- `app/Domains/Metrics/Services/MetricCacheService.php`
- `app/Domains/Metrics/Services/HotMetricService.php`
- `app/Domains/Metrics/Services/RedisRateLimiterService.php`
- `app/Http/Middleware/VerifyApiSignature.php`
- `routes/web.php`

## 源码类

- `Illuminate\Support\Facades\Facade`
- `Illuminate\Support\Facades\Cache`
- `Illuminate\Support\Facades\Redis`
- `Illuminate\Support\Facades\Route`
- `Illuminate\Container\Container`

## 核心机制

Facade 看起来像静态调用：

```php
Cache::put('key', 'value', 60);
```

底层不是直接调用静态业务逻辑，而是：

```text
Cache Facade
  -> getFacadeAccessor()
  -> 从容器解析 cache manager
  -> 转发方法到真实对象
```

因此 Facade 本质是容器服务的静态代理。

## 当前项目例子

- `MetricCacheService` 使用 `Cache::lock()` 演示分布式锁。
- `HotMetricService` 使用 `Redis::zincrby()` 和 `Redis::zrevrange()` 演示 ZSet。
- `VerifyApiSignature` 使用 `Cache::add()` 保存 nonce，防重放。
- `routes/web.php` 使用 `Cache::remember()` 缓存工作台统计。

## Facade 与依赖注入取舍

适合 Facade：

- 框架基础设施调用，例如 `Cache`、`Route`、`Event`。
- 简短、局部、不会影响测试替换的地方。

适合依赖注入：

- 业务服务，例如 `MetricCacheService`、`KafkaProducer`。
- 需要替换实现或单元测试隔离的服务。

## 生产风险

- Facade 静态外观容易隐藏真实依赖。
- 在 long-running worker 中，不要通过静态属性保存请求态。
- Facade 解析的是容器对象，容器绑定错了会影响所有调用。

## 基础问题

1. Facade 是真正的静态方法吗？
2. `Cache::remember()` 最终调用的是什么对象？
3. Facade 和 helper 有什么区别？
4. Facade 为什么还能被测试替换？
5. 当前项目哪些地方使用了 Facade？

## 资深追问

1. Facade 如何通过容器找到真实服务？
2. 为什么业务核心不宜全部写成 Facade 调用？
3. `Cache::lock()` 的 owner token 在哪里保存？
4. Redis Facade 和 Cache Facade 的边界是什么？
5. Octane 下 Facade 使用有什么额外注意事项？
