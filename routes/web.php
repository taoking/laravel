<?php

use Illuminate\Support\Facades\Route;

// 学习要点：web.php 由 RouteServiceProvider 加载，并自动放入 web 中间件组。
// web 组包含 cookie、session、CSRF 和路由模型绑定，适合浏览器页面。
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // view('welcome') 会渲染 resources/views/welcome.blade.php。
    // 示例闭包路由适合学习；真实业务建议使用 Controller 保持路由文件清晰。
    return view('welcome');
});
