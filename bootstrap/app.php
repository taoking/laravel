<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// 学习要点：bootstrap/app.php 是当前骨架最重要的启动配置文件。
// Laravel 10.x 会在这里手动 new Application 并绑定 Http Kernel / Console Kernel / Exception Handler。
// 当前本地 13.x 骨架改为 fluent API，把路由、中间件、异常处理集中声明在这里。
return Application::configure(basePath: dirname(__DIR__))
    // withRouting() 告诉框架从哪里加载路由和命令。
    // web 路由默认带有 Web 相关中间件能力，例如 session、cookie、CSRF 等。
    // commands 指向闭包式 Artisan 命令；health 会注册一个轻量健康检查端点。
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // withMiddleware() 是新骨架集中调整中间件栈的位置。
    // 常见操作：追加全局中间件、调整 web/api 分组、配置别名、设置 CSRF 例外 URI。
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    // withExceptions() 是集中配置异常处理的位置。
    // 常见操作：自定义 report/render、按异常类型返回 JSON、忽略不需要上报的异常。
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
