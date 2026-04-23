<?php

// 学习要点：Laravel 10 的 bootstrap/app.php 只负责创建应用和绑定核心契约。
// 它不直接处理 HTTP 请求或 CLI 命令；真正的处理逻辑交给 Http Kernel / Console Kernel。

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

// Application 是 Laravel 应用实例，也是服务容器。
// APP_BASE_PATH 允许测试或特殊部署重写根目录；默认使用项目根目录。
$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

// HTTP Kernel 负责 HTTP 请求生命周期：bootstrap 框架、执行中间件管道、路由分发和 terminate。
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

// Console Kernel 负责 Artisan 命令生命周期：注册命令、调度任务、执行命令和收尾。
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

// Exception Handler 负责异常上报和渲染，把异常转换成日志、错误页面或 JSON 响应。
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

// 返回 Application 给 public/index.php 或 artisan，由入口文件按场景解析对应 Kernel。
return $app;
