<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// 学习要点：routes/console.php 用于注册轻量闭包式 Artisan 命令。
// 复杂命令建议使用 `php artisan make:command` 生成独立命令类，便于测试和维护。
Artisan::command('inspire', function () {
    // $this 在闭包命令中代表底层 Command 实例，因此可以调用 comment/info/error 等输出方法。
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
