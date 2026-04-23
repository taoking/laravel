<?php

use App\Providers\AppServiceProvider;

// 学习要点：这里声明应用级 ServiceProvider。
// ServiceProvider 是 Laravel 的启动扩展点；框架和第三方包也通过 provider 把能力挂进容器。
// 当前新骨架把 provider 列表从 config/app.php 中拆到本文件。
return [
    AppServiceProvider::class,
];
