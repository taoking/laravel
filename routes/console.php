<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// 学习要点：console.php 由 App\Console\Kernel::commands() 加载。
// 它适合定义简单闭包命令；复杂命令建议使用 `php artisan make:command` 生成独立类。
/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    // 闭包命令中的 $this 绑定到底层 Command 实例，可以调用 comment/info/error 等输出方法。
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
