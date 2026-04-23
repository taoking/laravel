# Laravel 启动与关闭流程说明

本文专门说明 Laravel 应用从“被外部进程唤起”到“完成请求/命令并释放资源”的完整流程。这里的“关闭”不是指删除项目，而是指一次 HTTP 请求、一次 Artisan 命令、一个队列 worker 或维护模式切换时，Laravel 如何收尾。

## 版本背景

当前工作区是 `laravel/laravel` 应用骨架，当前本地分支为 `13.x`，`composer.json` 依赖 `laravel/framework:^13.0`。

需要区分两套骨架写法：

- Laravel 10.x：入口显式解析 `Http Kernel` / `Console Kernel`，再调用 `handle()` 和 `terminate()`。
- Laravel 11+ / 当前 13.x：入口更薄，HTTP 调用 `$app->handleRequest(...)`，CLI 调用 `$app->handleCommand(...)`，路由、中间件、异常配置集中在 `bootstrap/app.php`。

底层思想没有变：入口文件负责创建 Application，Application 作为服务容器启动框架组件，然后把请求或命令交给路由、控制台、队列等子系统处理，最后执行终止阶段。

## 启动与关闭的三个层次

理解 Laravel 生命周期时，要分清三层：

```text
操作系统 / 进程管理层
  Nginx、Apache、PHP-FPM、Supervisor、systemd、Docker、Herd、Valet

PHP 运行时层
  PHP 进程启动、加载 php.ini、加载扩展、执行脚本、请求结束后清理内存

Laravel 应用层
  public/index.php 或 artisan -> bootstrap/app.php -> Application -> 路由/命令 -> 响应/状态码 -> terminate
```

面试时不要把这三层混在一起。Laravel 自己不直接管理 Nginx 或 PHP-FPM 的生命周期，它只运行在 PHP 进程中。PHP-FPM、队列 worker、定时任务等由外部进程管理器负责启动和停止。

## HTTP 启动流程

当前本地 13.x 骨架的 HTTP 入口是 `public/index.php`：

```text
浏览器 / HTTP 客户端
  |
  v
Nginx / Apache / Herd / Valet
  |
  v
PHP-FPM 或内置开发服务器
  |
  v
public/index.php
  |
  |-- define('LARAVEL_START', microtime(true))
  |-- 检查 storage/framework/maintenance.php
  |-- require vendor/autoload.php
  |-- require bootstrap/app.php
  v
Illuminate\Foundation\Application
  |
  v
$app->handleRequest(Request::capture())
```

每一步的职责：

- `LARAVEL_START`：记录开始时间，方便后续统计请求耗时。
- 维护模式检查：如果存在 `storage/framework/maintenance.php`，在完整框架启动前返回维护响应。
- `vendor/autoload.php`：加载 Composer 自动加载器，使 `App\`、`Illuminate\` 和第三方包类可被定位。
- `bootstrap/app.php`：创建并配置 `Illuminate\Foundation\Application`。
- `Request::capture()`：从 PHP 全局变量构造请求对象。
- `handleRequest()`：进入 Laravel 框架层，完成 bootstrap、路由匹配、中间件、控制器、响应发送和终止阶段。

## `bootstrap/app.php` 启动配置

当前骨架中，`bootstrap/app.php` 是应用启动配置中心：

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

它主要做四件事：

- `configure(basePath: ...)`：声明项目根目录，让框架知道 `app/`、`config/`、`storage/`、`bootstrap/cache/` 等路径如何解析。
- `withRouting(...)`：声明 Web 路由、Console 命令路由、健康检查路由。
- `withMiddleware(...)`：集中配置 HTTP 中间件。
- `withExceptions(...)`：集中配置异常上报和渲染。

Laravel 10.x 中，这些职责更分散：

- `bootstrap/app.php` 创建 Application 并绑定 Kernel / Handler。
- `app/Http/Kernel.php` 管理中间件。
- `app/Console/Kernel.php` 管理命令调度。
- `app/Exceptions/Handler.php` 管理异常。
- `app/Providers/RouteServiceProvider.php` 管理路由加载。

## Application 启动阶段

Application 既是应用对象，也是服务容器。HTTP 或 CLI 第一次处理任务时，它会启动框架组件。

典型 bootstrap 动作包括：

```text
加载环境变量
加载 config/*.php
注册 Facade
注册基础服务提供者
注册应用和包的 ServiceProvider
执行 ServiceProvider::register()
执行 ServiceProvider::boot()
加载路由
准备异常处理、日志、事件、数据库、缓存等服务
```

重要顺序：

```text
register 阶段：把服务绑定进容器
boot 阶段：所有 provider 都注册后，执行需要依赖其他服务的初始化
```

面试回答：

> Laravel 启动不是一开始就创建所有对象，而是先建立容器和配置，注册服务提供者。大量服务是按需解析的，比如数据库连接通常在第一次查询时才真正建立。

## HTTP 运行阶段

请求进入 `handleRequest()` 后，大致流程如下：

```text
Request
  |
  v
全局中间件
  |
  v
路由匹配
  |
  v
路由分组中间件 web/api
  |
  v
路由中间件
  |
  v
控制器方法或路由闭包
  |
  v
Response
```

当前默认根路由在 `routes/web.php`：

```php
Route::get('/', function () {
    return view('welcome');
});
```

这个闭包返回一个视图。Laravel 会把视图渲染结果转换为 HTTP 响应。

中间件是管道模式：

```text
请求进入方向：
Middleware A -> Middleware B -> Middleware C -> Controller

响应返回方向：
Controller -> Middleware C -> Middleware B -> Middleware A
```

这解释了为什么中间件既能在控制器前处理请求，也能在控制器后处理响应。

## HTTP 关闭 / 终止阶段

HTTP 终止阶段通常发生在响应已经生成之后：

```text
控制器 / 路由闭包返回结果
  |
  v
Laravel 把结果转换为 Response
  |
  v
发送响应内容和响应头
  |
  v
执行 terminate middleware
  |
  v
执行 after-response / terminating 回调
  |
  v
PHP 请求结束，释放本次请求内存
  |
  v
PHP-FPM worker 回到进程池，等待下一个请求
```

终止阶段适合做这些事：

- 写访问日志或审计日志。
- 发送轻量 after-response 任务。
- 记录耗时指标。
- 关闭或清理本次请求上下文。

不适合做这些事：

- 执行很慢的业务任务。
- 等待外部接口很久。
- 做大文件处理。
- 做不可控的批量任务。

慢任务应该进入队列，而不是放在 terminate 阶段。

### `terminate` 中间件

如果一个中间件定义了 `terminate($request, $response)` 方法，Laravel 会在响应发送后调用它。

概念示例：

```php
public function terminate($request, $response): void
{
    // 记录请求耗时、状态码、用户 ID 等
}
```

面试重点：

> `handle()` 是请求处理链路的一部分，影响响应生成；`terminate()` 是响应发送后的收尾阶段，适合做轻量收尾工作。

### `dispatchAfterResponse`

Laravel 支持把某些任务延后到响应发送后再执行。它仍然发生在当前 PHP 进程内，不等于真正的后台队列。

适用场景：

- 请求成功后发送轻量通知。
- 写一条非关键日志。
- 做非常短的清理动作。

不适合：

- 图片处理。
- 邮件大批量发送。
- 长时间 API 调用。
- 任何需要失败重试的业务动作。

需要可靠异步时，用 queue worker。

### PHP-FPM 下的请求结束

在传统 PHP-FPM 模式中，每次请求的 Laravel Application 通常只在本次请求中存活：

```text
请求开始：创建 Application / 容器
请求处理：解析依赖、执行业务
请求结束：释放本次请求内存
worker 进程继续存活，等待下一个请求
```

因此不要把传统 PHP-FPM 下的单例误解为跨请求永久单例。Laravel 容器中的 `singleton()` 在一次请求内是单例；跨请求是否复用，取决于运行模式。

## CLI / Artisan 启动流程

当前本地 CLI 入口是 `artisan`：

```text
终端执行 php artisan xxx
  |
  v
artisan
  |
  |-- define('LARAVEL_START', microtime(true))
  |-- require vendor/autoload.php
  |-- require bootstrap/app.php
  v
$app->handleCommand(new ArgvInput)
  |
  v
解析命令、启动 Console Application、执行命令
  |
  v
返回状态码
  |
  v
exit($status)
```

Artisan 与 HTTP 共用：

- 同一套 Composer autoload。
- 同一个 Application 创建逻辑。
- 同一套 ServiceProvider。
- 同一套配置、日志、数据库、缓存、队列能力。

不同点：

- HTTP 处理的是 `Illuminate\Http\Request`。
- CLI 处理的是 Symfony Console 的 `ArgvInput`。
- HTTP 输出 Response。
- CLI 输出文本并返回进程状态码。

## CLI 关闭流程

一次普通 Artisan 命令结束时：

```text
命令 handle() 执行业务
  |
  v
返回 int 状态码或默认成功
  |
  v
Console Application 收尾
  |
  v
执行 terminating callbacks
  |
  v
PHP 进程退出
```

状态码约定：

- `0`：成功。
- 非 `0`：失败或异常。

这也是 CI/CD、脚本和部署系统判断命令是否成功的依据。

## 队列 Worker 启动与关闭

队列 worker 是长进程，不同于普通 HTTP 请求：

```text
php artisan queue:work
  |
  v
启动 Laravel Application
  |
  v
启动 Queue Worker
  |
  v
循环拉取 Job
  |
  v
执行 Job
  |
  v
继续等待下一个 Job
```

长期运行带来的关键差异：

- 容器和已加载类会在进程内长期存在。
- 修改代码后，worker 不会自动加载新代码。
- 静态变量、单例、内存缓存可能跨 Job 残留。
- 内存泄漏会积累。

因此部署后通常要重启 worker：

```bash
php artisan queue:restart
```

它的作用不是直接 kill 所有进程，而是让 worker 在完成当前任务后优雅退出。外部进程管理器再把 worker 拉起。

生产环境常见组合：

```text
Supervisor / systemd / Docker / Kubernetes
  |
  v
php artisan queue:work
```

关闭流程：

```text
收到 queue:restart 标记或系统信号
  |
  v
worker 不再领取新任务
  |
  v
尽量完成当前 Job
  |
  v
执行收尾
  |
  v
进程退出
  |
  v
进程管理器按配置重启
```

面试重点：

> 队列 worker 是长生命周期进程，所以部署后需要重启；Job 必须尽量幂等，因为失败重试、超时重跑、进程中断都可能导致同一任务被执行多次。

## Scheduler 启动与关闭

Laravel 定时任务通常由系统 cron 每分钟触发：

```cron
* * * * * php /path/to/artisan schedule:run
```

流程：

```text
cron 每分钟启动一次 PHP 进程
  |
  v
php artisan schedule:run
  |
  v
启动 Laravel Application
  |
  v
读取计划任务定义
  |
  v
判断当前分钟哪些任务应执行
  |
  v
执行命令 / 派发任务 / 调用闭包
  |
  v
命令结束，PHP 进程退出
```

如果使用 `schedule:work`，它会变成长进程，和 queue worker 一样需要考虑部署重启和内存积累。

## 维护模式启动与关闭

维护模式是 Laravel 的应用级开关，不是关闭 PHP-FPM 或 Web Server。

进入维护模式：

```bash
php artisan down
```

典型效果：

```text
生成 storage/framework/maintenance.php
  |
  v
后续 HTTP 请求进入 public/index.php
  |
  v
入口文件检测到 maintenance.php
  |
  v
提前返回维护响应
```

退出维护模式：

```bash
php artisan up
```

典型效果：

```text
删除 storage/framework/maintenance.php
  |
  v
后续 HTTP 请求正常启动 Laravel
```

面试回答：

> 维护模式不是停服务，而是在入口处短路请求，让应用避免继续走完整框架和业务流程。它适合迁移、发布或临时维护。

## 开发服务器启动与关闭

本地常用：

```bash
php artisan serve
```

流程：

```text
php artisan serve
  |
  v
启动 Laravel Console
  |
  v
启动 PHP 内置 Web Server
  |
  v
请求进入 public/index.php
```

关闭方式通常是：

```text
Ctrl+C
```

这会向进程发送中断信号，开发服务器退出。它只适合本地开发，不适合作为生产 Web Server。

## PHP-FPM / Web Server 的启动与关闭

生产常见结构：

```text
Nginx
  |
  v
PHP-FPM
  |
  v
Laravel public/index.php
```

启动顺序通常是：

```text
启动 PHP-FPM
启动 Nginx
请求进入 Nginx
Nginx 转发 PHP 请求给 PHP-FPM
PHP-FPM worker 执行 public/index.php
```

关闭或重载：

```text
Nginx reload：重新加载 Nginx 配置，尽量不中断连接
PHP-FPM reload：重新加载 PHP-FPM worker，适合 PHP 配置或扩展变更
PHP-FPM restart：完整重启，影响更大
```

Homebrew 本机 PHP-FPM 可用：

```bash
brew services start php@8.4
brew services stop php@8.4
brew services restart php@8.4
```

如果只是执行 `php artisan serve`，不需要启动 PHP-FPM。

## Octane / Swoole / RoadRunner 的特殊性

如果使用 Laravel Octane，生命周期会明显不同：

```text
启动 Octane Server
  |
  v
创建 Laravel Application
  |
  v
Application 常驻内存
  |
  v
多个请求复用同一个应用进程
```

这和 PHP-FPM 模式不同：

- 单例可能跨请求保留。
- 静态变量可能跨请求保留。
- 不应把请求级数据放入全局状态。
- 部署后需要 reload/restart Octane worker。

面试回答：

> PHP-FPM 下 Laravel 通常每个请求重新创建应用，Octane 下应用常驻内存，所以必须特别注意状态污染和内存泄漏。

## 异常情况下的关闭

如果运行中抛出异常：

```text
异常抛出
  |
  v
异常处理器 report
  |
  v
异常处理器 render
  |
  v
生成错误响应或 CLI 错误输出
  |
  v
执行可执行的终止阶段
```

HTTP 场景：

- 用户看到错误页面或 JSON 错误。
- 日志写入 `storage/logs/` 或配置的日志通道。
- 生产环境应关闭 `APP_DEBUG`。

CLI 场景：

- 命令输出错误信息。
- 进程返回非 0 状态码。
- CI/CD 或脚本据此判断失败。

## 配置缓存对启动的影响

生产部署常执行：

```bash
php artisan optimize
```

或分别执行：

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

影响：

- 配置缓存减少启动时读取多个配置文件的成本。
- 路由缓存减少大量路由注册的成本。
- 视图缓存提前编译 Blade。

注意：

```bash
php artisan optimize:clear
```

会清理这些缓存，适合本地调试或部署问题排查。

## 一次 HTTP 请求的完整时间线

```text
1. Nginx 接收请求
2. Nginx 判断不是静态文件，转发给 PHP-FPM
3. PHP-FPM worker 启动脚本 public/index.php
4. 入口定义 LARAVEL_START
5. 检查维护模式
6. 加载 Composer autoload
7. 加载 bootstrap/app.php
8. 创建 Application
9. 启动框架 bootstrapper
10. 加载配置和服务提供者
11. 捕获 Request
12. 路由匹配
13. 执行中间件管道
14. 调用控制器或路由闭包
15. 渲染视图或生成 JSON
16. 发送 Response
17. 执行 terminate middleware
18. 执行 terminating / after-response 回调
19. PHP 释放本次请求内存
20. PHP-FPM worker 等待下一次请求
```

## 一次 Artisan 命令的完整时间线

```text
1. shell 执行 php artisan migrate
2. artisan 定义 LARAVEL_START
3. 加载 Composer autoload
4. 加载 bootstrap/app.php
5. 创建 Application
6. handleCommand(new ArgvInput)
7. 启动 Console Application
8. 注册框架命令、包命令、应用命令
9. 匹配 migrate 命令
10. 执行迁移逻辑
11. 输出执行结果
12. 返回状态码
13. 执行终止回调
14. PHP 进程退出
```

## 常见命令与生命周期关系

| 命令 | 作用 | 生命周期特征 |
|---|---|---|
| `php artisan serve` | 启动本地开发服务器 | 长进程，Ctrl+C 关闭 |
| `php artisan down` | 进入维护模式 | 生成维护模式文件 |
| `php artisan up` | 退出维护模式 | 删除维护模式文件 |
| `php artisan queue:work` | 启动队列 worker | 长进程，需要部署后重启 |
| `php artisan queue:restart` | 优雅重启 worker | 通知 worker 完成当前任务后退出 |
| `php artisan schedule:run` | 执行当前分钟计划任务 | 短进程，通常由 cron 触发 |
| `php artisan schedule:work` | 常驻运行调度器 | 长进程，需要管理重启 |
| `php artisan optimize` | 缓存配置、路由、视图等 | 影响后续启动性能 |
| `php artisan optimize:clear` | 清理优化缓存 | 适合调试和部署排错 |

## 面试高频问法

### 1. Laravel 请求生命周期怎么说？

请求从 Web Server 进入 `public/index.php`，加载 Composer 自动加载器和 `bootstrap/app.php`，创建 Application，启动配置和服务提供者，捕获 Request，经过中间件管道和路由匹配，调用控制器或闭包，生成 Response，发送响应，最后执行 terminate 中间件和终止回调。

### 2. Laravel 什么时候真正连接数据库？

通常不是应用刚启动就连接，而是在第一次使用 DB、Eloquent 查询、迁移等数据库服务时按需连接。

### 3. `register()` 和 `boot()` 谁先执行？

所有 provider 的 `register()` 先执行，用来绑定服务；然后再执行 `boot()`，用来做依赖其他服务的初始化。

### 4. terminate 阶段适合做什么？

适合做轻量收尾，例如日志、指标、短耗时 after-response 动作。不适合做慢任务，慢任务应该进入队列。

### 5. 为什么队列 worker 部署后要重启？

因为 worker 是长进程，启动后会常驻内存。代码更新后，旧 worker 仍然运行旧代码，所以部署后需要 `queue:restart` 或重启进程。

### 6. PHP-FPM 和 Octane 生命周期有什么区别？

PHP-FPM 模式下应用通常按请求创建和释放；Octane 模式下应用常驻内存并被多个请求复用，所以更要注意状态污染、静态变量和内存泄漏。

### 7. 维护模式是不是关闭服务？

不是。维护模式只是让 Laravel 在入口处提前返回维护响应，Web Server 和 PHP-FPM 仍然运行。

## 推荐读源码位置

当前 13.x 骨架先读：

```text
public/index.php
bootstrap/app.php
artisan
bootstrap/providers.php
routes/web.php
routes/console.php
app/Providers/AppServiceProvider.php
config/*.php
```

如果安装了 `vendor/laravel/framework`，继续读：

```text
vendor/laravel/framework/src/Illuminate/Foundation/Application.php
vendor/laravel/framework/src/Illuminate/Foundation/Configuration/ApplicationBuilder.php
vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php
vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php
vendor/laravel/framework/src/Illuminate/Routing/Router.php
vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php
vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php
vendor/laravel/framework/src/Illuminate/Queue/Worker.php
vendor/laravel/framework/src/Illuminate/Console/Scheduling/ScheduleRunCommand.php
```

Laravel 10.x 骨架还要重点读：

```text
app/Http/Kernel.php
app/Console/Kernel.php
app/Exceptions/Handler.php
app/Providers/RouteServiceProvider.php
```
