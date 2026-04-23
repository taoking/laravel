<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 学习要点：public/index.php 是 Laravel 10 的 HTTP 单入口。
// Web Server 会把动态请求转发到这里；这里不写业务，只负责创建应用、解析 Kernel、处理请求和终止收尾。

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

// 维护模式短路：`php artisan down` 会生成 storage/framework/maintenance.php。
// 如果该文件存在，入口文件会在完整框架启动前加载它，避免继续执行路由和控制器。
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

// Composer 自动加载器：加载 App\、Illuminate\、Symfony、Sanctum 等命名空间类。
require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

// bootstrap/app.php 创建 Illuminate\Foundation\Application，并绑定 HTTP Kernel、Console Kernel、Exception Handler。
$app = require_once __DIR__.'/../bootstrap/app.php';

// 通过服务容器解析 HTTP Kernel。这里的 Kernel 契约在 bootstrap/app.php 中绑定到 App\Http\Kernel。
$kernel = $app->make(Kernel::class);

// Request::capture() 从 PHP 全局变量构造请求；handle() 执行框架启动、中间件、路由和控制器。
// send() 把响应头和响应体发送给客户端。
$response = $kernel->handle(
    $request = Request::capture()
)->send();

// 响应发送后进入终止阶段，执行 terminate middleware 和应用 terminating callbacks。
$kernel->terminate($request, $response);
