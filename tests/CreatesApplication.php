<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

// 学习要点：Laravel 10 的 Feature Test 通过这个 trait 创建并 bootstrap 应用。
// 它复用 bootstrap/app.php，再调用 Console Kernel 的 bootstrap() 完成配置和 Provider 启动。
trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // 测试环境也需要启动配置、异常处理、Facade 和服务提供者。
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
