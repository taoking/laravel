<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

// 学习要点：Console Kernel 是 Artisan 生命周期入口。
// artisan 文件通过容器解析它，然后调用 handle() 执行命令，最后调用 terminate() 收尾。
class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 定时任务在这里声明。生产环境通常由 cron 每分钟调用 `php artisan schedule:run`。
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // 加载 app/Console/Commands 下的命令类。
        $this->load(__DIR__.'/Commands');

        // 加载 routes/console.php 中的闭包式命令。
        require base_path('routes/console.php');
    }
}
