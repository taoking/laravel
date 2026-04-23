<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 学习要点：api.php 由 RouteServiceProvider 加载，默认拥有 /api 前缀，并使用 api 中间件组。
// 当前默认示例使用 auth:sanctum，说明 Laravel 10 骨架内置 Sanctum API 认证支持。
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    // 访问路径是 /api/user。认证通过后，$request->user() 返回当前用户模型。
    return $request->user();
});
