<?php

namespace App\Providers;

use App\Events\AuditEvent;
use App\Listeners\WriteAuditLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

// 学习要点：AppServiceProvider 是应用自己的默认启动扩展点。
// 如果要绑定接口实现、注册宏、配置模型约束或做全局初始化，通常从这里开始。
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        // register() 只负责“登记服务”，例如：
        // $this->app->bind(PaymentGateway::class, StripeGateway::class);
        // 面试重点：register 阶段应尽量避免使用还未 boot 的其他服务。
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // boot() 在所有 provider 注册完成后运行。
        // 适合注册事件监听、Blade 指令、路由模型绑定、Schema 默认长度、模型全局 scope 等。
        Event::listen(AuditEvent::class, WriteAuditLog::class);

        RateLimiter::for('metrics-query', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });
    }
}
