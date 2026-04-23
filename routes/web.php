<?php

use Illuminate\Support\Facades\Route;

// 学习要点：routes/web.php 定义面向浏览器的 Web 路由。
// 新骨架通过 bootstrap/app.php 的 withRouting(web: ...) 加载本文件。
// 面试时可以把 Route Facade 解释为“静态语法代理容器中的 router 服务”。
Route::get('/', function () {
    // view('welcome') 会解析 resources/views/welcome.blade.php，并返回可发送的 HTTP 响应。
    // 路由闭包适合演示或很薄的页面；真实业务建议指向 Controller，避免业务逻辑堆在路由文件里。
    return view('welcome');
});
