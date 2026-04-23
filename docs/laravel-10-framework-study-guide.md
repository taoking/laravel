# Laravel 10 框架学习与面试说明文档

本文基于当前 `10.x` 分支生成，适合从 Laravel 10 应用骨架阅读源码、理解框架流程，并准备面试。

## 当前源码范围

当前仓库是 `laravel/laravel`，它是 Laravel 应用骨架，不是完整框架核心仓库。

本分支关键版本信息：

- 分支：`10.x`
- 当前提交：`7effeb99`
- PHP 要求：`^8.1`
- 框架依赖：`laravel/framework:^10.10`
- 默认包含：`laravel/sanctum:^3.3`、`laravel/tinker:^2.8`

真正的框架核心代码来自 Composer 依赖，安装依赖后通常位于：

```text
vendor/laravel/framework/src/Illuminate/
```

本仓库可以直接学习的是应用启动入口、Kernel、Provider、路由、中间件、配置、模型、迁移、测试等骨架代码。

## Laravel 10 骨架核心特征

Laravel 10 的骨架比 Laravel 11+ 更显式，很多生命周期角色在应用目录中直接可见：

```text
public/index.php                    HTTP 入口
artisan                             CLI 入口
bootstrap/app.php                   创建 Application 并绑定 Kernel / Handler
app/Http/Kernel.php                 HTTP 中间件栈
app/Console/Kernel.php              Artisan 命令和调度
app/Exceptions/Handler.php          异常上报与渲染
app/Providers/RouteServiceProvider.php  路由加载与限流
config/app.php                      Provider 和 Facade alias 列表
```

面试概括：

> Laravel 10 的应用骨架把 HTTP Kernel、Console Kernel、Exception Handler、RouteServiceProvider 等生命周期组件直接放在应用层。请求进入 `public/index.php` 后，通过容器解析 HTTP Kernel，由 Kernel 启动框架、执行中间件管道、路由和控制器，最后发送响应并执行 terminate 阶段。

## 目录结构说明

```text
app/
  Console/Kernel.php                Console Kernel，调度任务和加载命令
  Exceptions/Handler.php            应用异常处理器
  Http/Kernel.php                   HTTP Kernel，中间件栈
  Http/Controllers/Controller.php   控制器基类
  Http/Middleware/                  应用中间件
  Models/User.php                   默认用户模型
  Providers/                        应用服务提供者
bootstrap/
  app.php                           创建 Application 并绑定核心契约
config/                             应用配置
database/
  migrations/                       数据库结构版本管理
  factories/                        测试数据工厂
  seeders/                          数据填充
public/index.php                    HTTP 单入口
routes/
  web.php                           Web 路由
  api.php                           API 路由
  console.php                       闭包式 Artisan 命令
  channels.php                      广播频道授权
tests/                              PHPUnit 测试
artisan                             CLI 单入口
```

## HTTP 请求生命周期

Laravel 10 的 HTTP 流程非常适合面试讲解：

```text
浏览器 / HTTP 客户端
  |
  v
public/index.php
  |
  |-- 定义 LARAVEL_START
  |-- 检查维护模式
  |-- require vendor/autoload.php
  |-- require bootstrap/app.php
  v
Application 容器
  |
  v
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class)
  |
  v
$response = $kernel->handle(Request::capture())->send()
  |
  v
$kernel->terminate($request, $response)
```

重点：

- `public/index.php` 是单入口，不写业务逻辑。
- `bootstrap/app.php` 创建 Application，并把 HTTP Kernel 契约绑定到 `App\Http\Kernel`。
- `Http\Kernel` 负责启动框架 bootstrapper 和执行中间件管道。
- `RouteServiceProvider` 负责加载 `routes/web.php` 和 `routes/api.php`。
- 控制器或路由闭包返回结果后，Laravel 转换为 Response 并发送。
- `terminate()` 阶段执行终止中间件和收尾回调。

## CLI / Artisan 生命周期

CLI 入口是 `artisan`：

```text
php artisan migrate
  |
  v
artisan
  |
  |-- 定义 LARAVEL_START
  |-- require vendor/autoload.php
  |-- require bootstrap/app.php
  v
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class)
  |
  v
$status = $kernel->handle(new ArgvInput, new ConsoleOutput)
  |
  v
$kernel->terminate($input, $status)
  |
  v
exit($status)
```

Console Kernel 负责：

- 注册 Artisan 命令。
- 加载 `app/Console/Commands`。
- 加载 `routes/console.php` 中的闭包命令。
- 定义定时任务 schedule。
- 执行命令并返回状态码。

## `bootstrap/app.php` 的职责

Laravel 10 的 `bootstrap/app.php` 做三件关键事：

1. 创建 Application：

```php
$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);
```

2. 绑定核心契约：

```php
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);
```

3. 返回 Application 给入口文件：

```php
return $app;
```

面试重点：

> `bootstrap/app.php` 把“创建应用实例”和“运行请求/命令”分离。入口文件拿到 Application 后，再按 HTTP 或 CLI 场景解析不同 Kernel。

## Application 与服务容器

`Illuminate\Foundation\Application` 既是应用对象，也是服务容器。

它负责：

- 保存基础路径。
- 注册基础服务。
- 管理服务提供者。
- 解析控制器、命令、事件监听器、队列任务等依赖。
- 作为 IoC Container 绑定接口到实现。

服务容器常用方法：

- `bind()`：每次解析创建新实例。
- `singleton()`：当前容器生命周期内复用同一个实例。
- `instance()`：把已有对象放入容器。
- `make()`：解析对象。

在 Laravel 10 的入口中：

```php
$kernel = $app->make(Kernel::class);
```

这就是通过容器解析 HTTP Kernel 的例子。

## HTTP Kernel

`app/Http/Kernel.php` 继承 `Illuminate\Foundation\Http\Kernel`。

它维护三类中间件：

1. 全局中间件 `$middleware`

每个 HTTP 请求都会经过，例如：

- `TrustProxies`
- `HandleCors`
- `PreventRequestsDuringMaintenance`
- `ValidatePostSize`
- `TrimStrings`
- `ConvertEmptyStringsToNull`

2. 中间件组 `$middlewareGroups`

默认有：

- `web`：cookie、session、CSRF、错误共享、路由绑定。
- `api`：限流、路由绑定，可选 Sanctum stateful middleware。

3. 中间件别名 `$middlewareAliases`

让路由可以写：

```php
Route::middleware('auth')->get(...);
Route::middleware('throttle:api')->get(...);
```

而不需要写完整类名。

面试回答：

> HTTP Kernel 是请求进入 Laravel 后的调度中心，它先启动框架，再把 Request 送入中间件管道，最终交给路由和控制器。

## Console Kernel

`app/Console/Kernel.php` 继承 `Illuminate\Foundation\Console\Kernel`。

核心方法：

- `schedule(Schedule $schedule)`：定义定时任务。
- `commands()`：加载应用命令和 `routes/console.php`。

定时任务示例：

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('inspire')->hourly();
}
```

生产环境通常由系统 cron 每分钟执行：

```cron
* * * * * php /path/to/artisan schedule:run
```

## Exception Handler

`app/Exceptions/Handler.php` 继承框架异常处理器。

它负责：

- 哪些输入字段不要闪存到 session。
- 注册异常上报回调。
- 注册异常渲染回调。
- 决定异常如何写日志、如何返回页面或 JSON。

默认 `$dontFlash` 包含：

- `current_password`
- `password`
- `password_confirmation`

这是为了避免验证失败时把敏感字段带回 session。

面试重点：

> 异常处理分 report 和 render。report 负责记录和上报，render 负责把异常转换成 HTTP 响应。

## Service Provider

Service Provider 是 Laravel 的启动扩展点。Laravel 10 的 Provider 列表位于 `config/app.php`：

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
])->toArray(),
```

常见 Provider：

- `AppServiceProvider`：应用通用绑定和启动逻辑。
- `AuthServiceProvider`：授权策略和 Gate。
- `EventServiceProvider`：事件和监听器。
- `RouteServiceProvider`：路由、限流、路由模型绑定。
- `BroadcastServiceProvider`：广播频道，默认注释掉。

`register()` 与 `boot()` 区别：

- `register()`：只做容器绑定。
- `boot()`：所有 provider 注册后执行，适合依赖其他服务的初始化。

## RouteServiceProvider

Laravel 10 的 `RouteServiceProvider` 非常重要。

默认逻辑：

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

$this->routes(function () {
    Route::middleware('api')
        ->prefix('api')
        ->group(base_path('routes/api.php'));

    Route::middleware('web')
        ->group(base_path('routes/web.php'));
});
```

它做两件事：

- 定义 `api` 限流规则。
- 加载 `routes/api.php` 和 `routes/web.php`。

面试回答：

> Laravel 10 中路由不是入口文件直接加载的，而是在框架启动 provider 时由 RouteServiceProvider 加载。web 路由带 web 中间件组，api 路由带 api 前缀和 api 中间件组。

## 路由文件

### `routes/web.php`

面向浏览器页面，默认使用 `web` 中间件组。

能力包括：

- Cookie 加密
- Session
- CSRF
- 共享验证错误
- 路由模型绑定

### `routes/api.php`

面向 API，默认前缀是 `/api`。

默认示例：

```php
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
```

这表示请求 `/api/user` 时，需要通过 Sanctum 认证。

### `routes/console.php`

注册闭包式 Artisan 命令。

### `routes/channels.php`

注册广播频道授权回调。

## Facade

Laravel 10 中常见 Facade：

- `Route`
- `Artisan`
- `Broadcast`
- `Schema`
- `RateLimiter`
- `Event`

Facade 不是普通静态类，而是服务容器代理。

面试回答：

> Facade 使用静态调用语法，但实际会从容器解析底层服务对象并转发调用。优点是简洁，缺点是依赖不够显式；复杂业务里我会更倾向构造函数注入核心依赖。

## 配置系统

配置文件位于 `config/`，启动时被加载到配置仓库。

业务代码应使用：

```php
config('app.name');
config('database.default');
```

而不是到处直接读取：

```php
env('APP_NAME');
```

原因：

> 生产环境通常执行 `php artisan config:cache`。配置缓存后，业务代码直接 `env()` 可能拿不到预期值。`.env` 应作为配置文件的输入，业务运行时通过 `config()` 读取。

## 认证与 Sanctum

Laravel 10 默认包含 Sanctum。

认证配置在 `config/auth.php`：

- guard：定义如何认证当前请求。
- provider：定义用户数据从哪里取。

默认 guard：

```php
'web' => [
    'driver' => 'session',
    'provider' => 'users',
],
```

Sanctum 相关：

- `config/sanctum.php`
- `Laravel\Sanctum\HasApiTokens`
- `personal_access_tokens` 表
- `auth:sanctum` 中间件

Sanctum 支持两类典型场景：

- SPA 首方应用：基于 session/cookie。
- API token：基于 bearer token。

## Eloquent User 模型

`app/Models/User.php` 继承：

```php
Illuminate\Foundation\Auth\User
```

默认 trait：

- `HasApiTokens`：Sanctum token 能力。
- `HasFactory`：模型工厂。
- `Notifiable`：通知能力。

默认字段控制：

- `$fillable`：允许批量赋值的字段。
- `$hidden`：序列化时隐藏的字段。
- `$casts`：字段类型转换。

`password => hashed` 是 Laravel 10 的重要默认设置，赋值时自动哈希。

## Migration

默认迁移包括：

- `users`
- `password_reset_tokens`
- `failed_jobs`
- `personal_access_tokens`

Laravel 10 这个骨架默认没有创建 `jobs` 表。如果你要使用 database queue，通常需要执行：

```bash
php artisan queue:table
php artisan migrate
```

如果要使用 database session，也需要生成 session 表：

```bash
php artisan session:table
php artisan migrate
```

## 队列

`config/queue.php` 默认：

```php
'default' => env('QUEUE_CONNECTION', 'sync'),
```

`sync` 表示任务同步执行，不进入后台队列。生产环境常用：

- `database`
- `redis`
- `sqs`

面试重点：

> 队列用于把慢任务移出 HTTP 请求链路。生产中要关注 worker 进程管理、任务幂等、失败重试、超时、失败任务告警和部署后重启 worker。

## Session 与 Cache

Laravel 10 默认：

- session driver：`file`
- cache driver：`file`

因此刚创建的项目不依赖数据库即可运行。

生产常见选择：

- Session：Redis 或 database。
- Cache：Redis、Memcached。
- Lock：Redis 更适合高并发原子锁。

## 测试

测试结构：

- `tests/Feature`：启动 Laravel 应用，测试 HTTP 流程、认证、数据库等。
- `tests/Unit`：不启动完整应用，测试纯逻辑。
- `tests/CreatesApplication.php`：测试启动应用的 trait。
- `tests/TestCase.php`：Feature Test 基类。

默认执行：

```bash
php artisan test
```

或：

```bash
vendor/bin/phpunit
```

## Laravel 10 与 13.x 骨架差异

当前 10.x：

- 有 `app/Http/Kernel.php`。
- 有 `app/Console/Kernel.php`。
- 有 `app/Exceptions/Handler.php`。
- 有 `app/Providers/RouteServiceProvider.php`。
- Provider 列表在 `config/app.php`。
- HTTP 入口显式调用 `$kernel->handle(...)->send()` 和 `$kernel->terminate(...)`。

新骨架 11+ / 13.x：

- 应用层不再默认暴露这些 Kernel/Handler 文件。
- 路由、中间件、异常集中在 `bootstrap/app.php`。
- HTTP 入口直接调用 `$app->handleRequest(...)`。
- CLI 入口直接调用 `$app->handleCommand(...)`。

面试回答：

> Laravel 10 的骨架更适合学习生命周期，因为 Kernel、Handler、RouteServiceProvider 都在应用层可见；新骨架减少样板文件，但底层框架能力仍然存在，只是配置入口更集中。

## 推荐读源码顺序

先读应用骨架：

1. `public/index.php`
2. `bootstrap/app.php`
3. `app/Http/Kernel.php`
4. `app/Console/Kernel.php`
5. `app/Exceptions/Handler.php`
6. `config/app.php`
7. `app/Providers/RouteServiceProvider.php`
8. `routes/web.php`
9. `routes/api.php`
10. `app/Models/User.php`
11. `database/migrations/*.php`
12. `config/auth.php`
13. `config/sanctum.php`
14. `config/session.php`
15. `config/cache.php`
16. `config/queue.php`
17. `tests/*`

安装依赖后继续读框架核心：

```text
vendor/laravel/framework/src/Illuminate/Foundation/Application.php
vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php
vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php
vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php
vendor/laravel/framework/src/Illuminate/Container/Container.php
vendor/laravel/framework/src/Illuminate/Routing/Router.php
vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php
vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php
vendor/laravel/framework/src/Illuminate/Support/Facades/Facade.php
vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php
vendor/laravel/framework/src/Illuminate/Queue/Worker.php
```

## 面试高频问题

### 1. Laravel 10 请求生命周期是什么？

请求进入 `public/index.php`，加载 autoload 和 `bootstrap/app.php`，创建 Application，通过容器解析 HTTP Kernel，Kernel 启动框架、加载配置和服务提供者，执行中间件管道，路由匹配，调用控制器或闭包，生成并发送 Response，最后执行 Kernel 的 `terminate()`。

### 2. `bootstrap/app.php` 做什么？

创建 Application 容器，并把 HTTP Kernel、Console Kernel、Exception Handler 的契约绑定到应用实现，最后返回 Application 给入口文件。

### 3. HTTP Kernel 负责什么？

负责 HTTP 请求的框架启动、中间件管理和请求分发。它维护全局中间件、中间件组和中间件别名。

### 4. RouteServiceProvider 负责什么？

定义路由相关启动逻辑，例如 API 限流，并加载 `routes/api.php` 与 `routes/web.php`。

### 5. ServiceProvider 的 `register()` 和 `boot()` 区别？

`register()` 用于绑定服务到容器；`boot()` 在所有 provider 注册完成后运行，适合注册事件、路由模型绑定、宏、策略等需要其他服务参与的初始化。

### 6. Facade 原理是什么？

Facade 是容器服务的静态代理。调用 `Route::get()` 时，Facade 会从容器解析路由器实例，再把调用转发给真实对象。

### 7. `auth:sanctum` 做什么？

通过 Sanctum 认证请求。它可以检查首方 SPA session/cookie，也可以检查 bearer token，适合轻量 API token 和 SPA 认证。

### 8. 队列为什么默认是 sync？

新项目开箱即用，不依赖额外 worker 或队列表。生产环境为了性能和可靠性，通常改成 database、redis 或 sqs。

### 9. Laravel 10 默认 session/cache 为什么用 file？

降低本地启动成本，不需要数据库或 Redis 即可运行。生产环境可按部署规模切换到 Redis、database 或 Memcached。

### 10. Laravel 10 和新骨架最大的区别？

Laravel 10 暴露 Kernel、Handler、RouteServiceProvider 等应用层文件；新骨架把中间件、异常、路由等配置集中到 `bootstrap/app.php`，减少默认文件数量。
