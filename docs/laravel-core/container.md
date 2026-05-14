# Laravel 服务容器源码追问

## 项目入口

- `app/Http/Controllers/Api/V1/Metrics/MetricController.php`
- `app/Domains/Access/Services/PermissionService.php`
- `app/Domains/Metrics/Services/MetricCacheService.php`
- `app/Domains/Messaging/KafkaProducer.php`
- `app/Console/Commands/RedisCacheLabCommand.php`

## 源码类

- `Illuminate\Foundation\Application`
- `Illuminate\Container\Container`
- `Illuminate\Contracts\Container\Container`
- `Illuminate\Routing\ControllerDispatcher`
- `Illuminate\Console\Command`

## 执行链

以 `MetricController@show` 为例：

```text
Router 匹配 /api/v1/metrics/{metric}
  -> 解析 Route Model Binding 得到 Metric
  -> ControllerDispatcher 读取方法参数
  -> Container 解析 HotMetricService
  -> Container 解析 MetricCacheService
  -> 调用 MetricController@show
```

Laravel 控制器方法不需要手动 `new MetricCacheService()`，因为容器可以根据类型声明自动解析没有复杂构造参数的类。Kafka 模块更典型：`KafkaProducer` 构造函数依赖 `KafkaClient` 和 `KafkaMessageFactory`，其中 `KafkaClient` 在 `AppServiceProvider` 中绑定到具体 driver。

## 面试回答

服务容器解决三个问题：

- 依赖创建：不在业务代码里散落 `new`。
- 依赖替换：接口可以绑定到不同实现，测试和生产可以使用不同 driver。
- 生命周期管理：可以按普通绑定、单例、已有实例管理对象。

当前项目中的例子：

- `MetricCacheService` 自动注入到 Controller。
- `KafkaProducer` 通过容器拿到具体 `KafkaClient`。
- Artisan 命令 `RedisCacheLabCommand` 自动注入 `RedisRateLimiterService`。

## 生产风险

- 容器无法解析接口时，会出现 binding resolution 异常。
- 构造函数依赖过多通常说明类职责过重。
- 在 Service Provider 中绑定闭包时，不要提前访问请求态对象。
- 单例服务不要保存请求级状态，否则在 Octane 或 Worker 中可能串数据。

## 基础问题

1. Laravel 服务容器解决了什么问题？
2. 控制器方法参数里的服务对象是谁创建的？
3. `bind()` 和 `singleton()` 有什么区别？
4. 接口没有绑定具体实现时会发生什么？
5. 为什么不建议在业务代码里到处 `new` 服务类？

## 资深追问

1. `MetricController@show` 里的 `MetricCacheService` 是如何被解析的？
2. `KafkaClient` 为什么必须在 `AppServiceProvider` 里绑定？
3. 如果服务构造函数依赖标量参数，容器如何处理？
4. Octane 下 singleton 保存请求态数据会有什么风险？
5. 如何在测试中替换容器里的实现？
