<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

// 学习要点：EventServiceProvider 管理事件和监听器映射。
// Laravel 启动时会根据这里的配置注册事件监听关系。
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // 用户注册后发送邮箱验证通知，这是 Laravel 默认认证流程中的一个事件监听示例。
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // 也可以在这里手动 Event::listen(...) 注册监听器。
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        // false 表示不自动扫描事件监听器，使用上面的 $listen 显式映射。
        return false;
    }
}
