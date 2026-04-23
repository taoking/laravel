<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// 学习要点：public/index.php 是 HTTP 请求的单入口。
// Nginx/Apache 会把所有非静态文件请求转发到这里；这里不写业务逻辑，只负责启动框架并交给 Application 处理。

// 维护模式短路：执行 `php artisan down` 后，Laravel 会生成 maintenance.php。
// 如果该文件存在，框架会在完整启动前返回维护响应，避免继续解析路由、连接服务或执行控制器。
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer 自动加载器：负责加载 App\、Illuminate\、第三方包等类。
// 没有 vendor/autoload.php，后面的 Application、Request、Facade、ServiceProvider 都无法被定位。
require __DIR__.'/../vendor/autoload.php';

// bootstrap/app.php 创建并配置 Illuminate\Foundation\Application。
// 当前新骨架中，HTTP Kernel 的显式代码被收敛到 Application::handleRequest() 内部。
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Request::capture() 从 PHP 全局变量构造请求对象。
// handleRequest() 会完成框架启动、路由匹配、中间件管道、控制器调用、响应发送和终止回调。
$app->handleRequest(Request::capture());
