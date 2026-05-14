# Laravel ServiceProvider 源码追问

## 项目入口

- `app/Providers/AppServiceProvider.php`
- `bootstrap/providers.php`
- `config/kafka.php`
- `app/Domains/Messaging/Contracts/KafkaClient.php`

## 源码类

- `Illuminate\Support\ServiceProvider`
- `Illuminate\Foundation\ProviderRepository`
- `Illuminate\Foundation\Application`
- `Illuminate\Foundation\Configuration\ApplicationBuilder`

## 执行链

```text
bootstrap/app.php
  -> Application::configure(...)
  -> 读取 bootstrap/providers.php
  -> 注册 AppServiceProvider
  -> 执行 register()
  -> 所有 provider 注册完成
  -> 执行 boot()
```

当前项目在 `AppServiceProvider::register()` 中绑定 Kafka 相关服务：

- `KafkaClient` 根据 `config('kafka.driver')` 选择 local 或 docker driver。
- `KafkaProducer`、`KafkaConsumerService` 通过容器统一创建。
- Kafka handlers 通过 tag 收集。

在 `boot()` 中注册运行期能力：

- `Event::listen(AuditEvent::class, WriteAuditLog::class)`
- `RateLimiter::for('metrics-query', ...)`

## register 与 boot

`register()`：

- 只负责向容器登记服务。
- 不应依赖其他 provider 的 boot 结果。
- 适合 `bind()`、`singleton()`、`tag()`。

`boot()`：

- 所有 provider register 完成后执行。
- 适合注册事件监听、限流器、模型约束、宏、视图 composer。

## 生产风险

- 在 `register()` 中执行数据库查询会拉长启动时间。
- provider 里读取未缓存配置时，生产发布要注意 `config:cache`。
- long-running worker 更新 provider 代码后需要重启。
- 绑定 driver 时要保证测试环境有可离线运行的实现。

## 基础问题

1. ServiceProvider 的作用是什么？
2. `register()` 和 `boot()` 有什么区别？
3. 当前项目在哪里绑定 Kafka driver？
4. RateLimiter 为什么放在 `boot()`？
5. `bootstrap/providers.php` 和旧版 `config/app.php` providers 有什么区别？

## 资深追问

1. 如果 `KafkaClient` 没有绑定，`KafkaProducer` 会如何失败？
2. 为什么 provider 里不要做重查询或远程请求？
3. `config:cache` 后配置读取行为有什么变化？
4. 包的 ServiceProvider 是如何被发现和注册的？
5. Queue Worker 部署后为什么需要重启才能加载 provider 新代码？
