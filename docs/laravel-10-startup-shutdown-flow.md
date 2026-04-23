# Laravel 10 启动与关闭流程说明

本文专门说明 Laravel 10 应用从 HTTP/CLI 入口启动，到响应发送、命令结束、队列 worker 退出、维护模式切换等“关闭/收尾”流程。

## 总览

Laravel 10 的生命周期入口很清晰：

```text
HTTP 请求：public/index.php -> bootstrap/app.php -> App\Http\Kernel
CLI 命令：artisan -> bootstrap/app.php -> App\Console\Kernel
异常处理：bootstrap/app.php -> App\Exceptions\Handler
路由加载：App\Providers\RouteServiceProvider
中间件栈：App\Http\Kernel
```

这套结构是学习 Laravel 生命周期的经典版本。

## HTTP 启动流程

一次 HTTP 请求从外到内：

```text
浏览器 / HTTP 客户端
  |
  v
Nginx / Apache / Valet / Herd / php artisan serve
  |
  v
PHP 运行 public/index.php
  |
  v
加载 Composer autoload
  |
  v
加载 bootstrap/app.php
  |
  v
创建 Illuminate\Foundation\Application
  |
  v
解析 Illuminate\Contracts\Http\Kernel
  |
  v
App\Http\Kernel::handle($request)
```

`public/index.php` 关键代码：

```php
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
```

这几行就是 Laravel 10 HTTP 生命周期主线。

## `public/index.php` 分解

### 1. 标记启动时间

```php
define('LARAVEL_START', microtime(true));
```

用于统计应用启动和请求耗时。

### 2. 维护模式短路

```php
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}
```

执行 `php artisan down` 后会生成维护模式文件。入口文件检测到后，可以在完整框架启动前返回维护响应。

### 3. 加载 Composer

```php
require __DIR__.'/../vendor/autoload.php';
```

这一步让 PHP 能通过命名空间加载应用类、Laravel 框架类和第三方包。

### 4. 创建 Application

```php
$app = require_once __DIR__.'/../bootstrap/app.php';
```

`bootstrap/app.php` 返回 Laravel Application。

### 5. 解析 HTTP Kernel

```php
$kernel = $app->make(Kernel::class);
```

这里的 `Kernel::class` 是 `Illuminate\Contracts\Http\Kernel`。具体实现由 `bootstrap/app.php` 绑定到 `App\Http\Kernel`。

### 6. 捕获并处理请求

```php
$response = $kernel->handle(
    $request = Request::capture()
)->send();
```

`Request::capture()` 从 PHP 全局变量生成请求对象。`handle()` 处理请求并返回响应。`send()` 把响应头和响应体发给客户端。

### 7. 执行终止阶段

```php
$kernel->terminate($request, $response);
```

响应发送后，执行 terminate middleware 和应用终止回调。

## `bootstrap/app.php` 启动流程

Laravel 10 的 `bootstrap/app.php` 是容器创建和核心契约绑定文件。

流程：

```text
new Illuminate\Foundation\Application
  |
  v
绑定 HTTP Kernel
  |
  v
绑定 Console Kernel
  |
  v
绑定 Exception Handler
  |
  v
return $app
```

关键绑定：

```php
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);
```

表示当容器需要 HTTP Kernel 契约时，返回 `App\Http\Kernel`。

```php
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);
```

表示 Artisan 命令由 `App\Console\Kernel` 处理。

```php
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);
```

表示异常处理由 `App\Exceptions\Handler` 负责。

面试回答：

> Laravel 10 的 bootstrap/app.php 不直接处理请求，它只创建 Application 并绑定核心契约。真正处理 HTTP 的是 Http Kernel，处理 CLI 的是 Console Kernel。

## HTTP Kernel 启动框架

`App\Http\Kernel` 继承框架 Kernel。它的核心职责：

```text
接收 Request
  |
  v
启动框架 bootstrapper
  |
  v
执行全局中间件
  |
  v
交给 Router
  |
  v
执行路由中间件
  |
  v
调用控制器或闭包
  |
  v
返回 Response
```

框架 bootstrapper 通常会处理：

- 加载环境变量。
- 加载配置。
- 配置异常处理。
- 注册 Facade。
- 注册服务提供者。
- 启动服务提供者。

## 中间件管道

Laravel 10 的中间件位置在 `app/Http/Kernel.php`。

全局中间件：

```text
每个请求都执行
```

Web 中间件组：

```text
cookie -> session -> csrf -> route binding
```

API 中间件组：

```text
throttle -> route binding
```

执行模型：

```text
Request
  -> Global Middleware
  -> Route Group Middleware
  -> Route Middleware
  -> Controller / Closure
  -> Response
```

响应返回时按相反方向穿过中间件。

## 路由加载流程

Laravel 10 由 `RouteServiceProvider` 加载路由：

```text
ServiceProvider 启动
  |
  v
RouteServiceProvider::boot()
  |
  |-- 定义 api rate limiter
  |-- 加载 routes/api.php
  |-- 加载 routes/web.php
```

默认规则：

```text
routes/web.php
  使用 web 中间件组
  无默认 URI 前缀

routes/api.php
  使用 api 中间件组
  默认 URI 前缀 /api
```

所以 `routes/api.php` 中的：

```php
Route::middleware('auth:sanctum')->get('/user', ...);
```

实际访问路径是：

```text
/api/user
```

## 响应生成流程

控制器或路由闭包可以返回：

- 字符串
- 数组
- View
- Redirect
- JsonResponse
- Response 对象
- Eloquent Model / Collection

Laravel 会把返回值标准化为 Symfony Response，然后发送。

示例：

```php
Route::get('/', function () {
    return view('welcome');
});
```

流程：

```text
view('welcome')
  |
  v
解析 resources/views/welcome.blade.php
  |
  v
编译 Blade 到 storage/framework/views
  |
  v
生成 HTML
  |
  v
返回 Response
```

## HTTP 关闭 / 终止流程

响应发送后并不代表 Laravel 立刻结束。Laravel 10 会继续执行：

```text
$kernel->terminate($request, $response)
  |
  v
调用 terminate middleware
  |
  v
执行 Application terminating callbacks
  |
  v
PHP 请求结束
  |
  v
PHP-FPM worker 等待下一个请求
```

适合放在 terminate 阶段的任务：

- 请求日志。
- 简单指标统计。
- 短耗时 after-response 动作。

不适合：

- 大文件处理。
- 批量发送邮件。
- 长时间请求第三方接口。
- 需要失败重试的任务。

这些应该进入队列。

## Artisan 启动流程

CLI 入口是 `artisan`：

```text
shell
  |
  v
php artisan xxx
  |
  v
artisan
  |
  |-- require vendor/autoload.php
  |-- require bootstrap/app.php
  v
$kernel = $app->make(ConsoleKernel::class)
  |
  v
$kernel->handle($input, $output)
```

关键代码：

```php
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->handle(
    $input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);

$kernel->terminate($input, $status);

exit($status);
```

## Console Kernel 流程

`App\Console\Kernel` 做两件事：

```text
schedule()
  定义定时任务

commands()
  加载 app/Console/Commands
  加载 routes/console.php
```

闭包命令示例：

```php
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
});
```

复杂命令建议使用：

```bash
php artisan make:command SomeCommand
```

## CLI 关闭流程

普通 Artisan 命令执行结束后：

```text
命令 handle() 返回
  |
  v
Console Kernel 得到状态码
  |
  v
Console Kernel terminate()
  |
  v
PHP 进程 exit($status)
```

状态码：

- `0`：成功。
- 非 `0`：失败。

这对 shell 脚本、CI/CD、部署流程很重要。

## 队列 Worker 启动与关闭

Laravel 10 默认队列连接是 `sync`。如果改为 Redis/database 并启动 worker：

```bash
php artisan queue:work
```

流程：

```text
启动 artisan
  |
  v
启动 Console Kernel
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
继续等待
```

队列 worker 是长进程。它和普通 HTTP 请求不同：

- 代码不会每个 Job 自动重新加载。
- 容器单例可能跨 Job 存在。
- 静态变量可能跨 Job 保留。
- 内存泄漏会积累。

部署后应执行：

```bash
php artisan queue:restart
```

它会通知 worker 在当前任务完成后优雅退出。Supervisor、systemd 或容器编排再重新拉起进程。

## Scheduler 启动与关闭

Laravel 10 定时任务通常由 cron 触发：

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
启动 Laravel
  |
  v
读取 Console Kernel schedule()
  |
  v
执行当前分钟应运行的任务
  |
  v
PHP 进程退出
```

如果使用 `schedule:work`，它就是长进程，需要像队列 worker 一样考虑重启。

## 维护模式启动与关闭

进入维护模式：

```bash
php artisan down
```

结果：

```text
生成 storage/framework/maintenance.php
  |
  v
后续 HTTP 请求进入 public/index.php
  |
  v
入口检测维护模式文件
  |
  v
提前返回维护响应
```

退出维护模式：

```bash
php artisan up
```

结果：

```text
删除 storage/framework/maintenance.php
  |
  v
后续请求正常进入完整 Laravel 生命周期
```

维护模式不是关闭 Nginx、PHP-FPM 或队列 worker，它只是让 HTTP 入口提前短路。

## PHP-FPM 模式的进程收尾

传统生产部署：

```text
Nginx
  |
  v
PHP-FPM
  |
  v
public/index.php
```

每次请求：

```text
PHP-FPM worker 执行 Laravel
  |
  v
请求结束后释放本次请求内存
  |
  v
worker 进程继续存在，等待下一个请求
```

Laravel 容器中的 `singleton()` 在一次请求中是单例。跨请求是否复用，取决于运行模式；在普通 PHP-FPM 下，不应依赖跨请求状态。

## `php artisan serve` 启动与关闭

本地开发常用：

```bash
php artisan serve
```

它会启动 PHP 内置 Web Server，然后请求仍然进入 `public/index.php`。

关闭方式：

```text
Ctrl+C
```

它只适合本地开发，不适合生产。

## 配置缓存影响启动

生产常用：

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

作用：

- 减少启动时加载配置文件的成本。
- 减少路由注册成本。
- 提前编译 Blade 视图。

清理：

```bash
php artisan optimize:clear
```

注意：配置缓存后，业务代码应使用 `config()`，不要直接依赖 `env()`。

## 异常时的收尾

HTTP 异常流程：

```text
业务代码抛异常
  |
  v
App\Exceptions\Handler report
  |
  v
App\Exceptions\Handler render
  |
  v
生成错误页面或 JSON
  |
  v
发送响应
  |
  v
执行 terminate 阶段
```

CLI 异常流程：

```text
命令抛异常
  |
  v
异常处理器记录日志
  |
  v
Console 输出错误
  |
  v
返回非 0 状态码
```

生产环境应关闭：

```text
APP_DEBUG=false
```

## 一次 HTTP 请求完整时间线

```text
1. Nginx / PHP 开发服务器收到请求
2. 请求进入 public/index.php
3. 定义 LARAVEL_START
4. 检查维护模式
5. 加载 vendor/autoload.php
6. 加载 bootstrap/app.php
7. 创建 Application
8. 绑定 HTTP Kernel / Console Kernel / Exception Handler
9. 容器解析 App\Http\Kernel
10. Request::capture() 捕获请求
11. HTTP Kernel 启动框架 bootstrapper
12. 加载配置和服务提供者
13. RouteServiceProvider 加载路由
14. 执行全局中间件
15. 匹配路由
16. 执行路由中间件
17. 调用控制器或闭包
18. 生成 Response
19. send() 发送响应
20. terminate() 执行收尾
21. PHP 请求结束
```

## 一次 Artisan 命令完整时间线

```text
1. shell 执行 php artisan xxx
2. artisan 定义 LARAVEL_START
3. 加载 vendor/autoload.php
4. 加载 bootstrap/app.php
5. 创建 Application
6. 绑定 Console Kernel
7. 容器解析 App\Console\Kernel
8. 解析命令行参数 ArgvInput
9. Console Kernel 加载命令
10. 执行目标命令
11. 返回状态码
12. Console Kernel terminate()
13. exit($status)
```

## 面试常见问法

### 1. Laravel 10 中 `handle()` 和 `terminate()` 区别？

`handle()` 负责处理请求并生成响应；`terminate()` 在响应发送后执行收尾逻辑，如 terminate middleware 和 terminating callbacks。

### 2. 为什么 `bootstrap/app.php` 要绑定接口？

入口文件依赖的是契约，例如 `Illuminate\Contracts\Http\Kernel`。容器通过绑定知道要实例化 `App\Http\Kernel`，这让框架层和应用实现解耦。

### 3. 维护模式为什么能提前返回？

因为 `public/index.php` 在加载完整框架前就检查 `storage/framework/maintenance.php`。文件存在时可直接返回维护响应。

### 4. 队列 worker 和 HTTP 请求生命周期有什么不同？

HTTP 请求通常是短生命周期；queue worker 是长进程，会连续处理多个 Job，所以需要关注内存泄漏、状态残留、部署重启和任务幂等。

### 5. 路由什么时候加载？

Laravel 10 中路由由 `RouteServiceProvider::boot()` 加载。它把 `routes/api.php` 放到 `api` 中间件组并加 `/api` 前缀，把 `routes/web.php` 放到 `web` 中间件组。

### 6. 配置缓存对启动有什么影响？

配置缓存把多个配置文件编译为一个缓存文件，减少启动成本。缓存后业务代码应使用 `config()`，不要直接使用 `env()`。

## 推荐源码阅读顺序

```text
public/index.php
bootstrap/app.php
app/Http/Kernel.php
app/Console/Kernel.php
app/Exceptions/Handler.php
config/app.php
app/Providers/RouteServiceProvider.php
routes/web.php
routes/api.php
artisan
config/queue.php
config/session.php
config/cache.php
```
