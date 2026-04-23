<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// 学习要点：AppServiceProvider 是应用默认扩展点。
// 适合放应用级容器绑定、宏、模型约束、全局初始化等逻辑。
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // register() 只负责向容器登记服务，例如绑定接口到实现。
        // 避免在这里依赖还没有 boot 完成的服务。
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // boot() 在所有 provider 注册后执行，适合做需要其他服务参与的初始化。
        //
    }
}
