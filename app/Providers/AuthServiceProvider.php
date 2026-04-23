<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

// 学习要点：AuthServiceProvider 用于注册授权策略和 Gate。
// 认证解决“你是谁”，授权解决“你能不能做这件事”。
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 示例：Post::class => PostPolicy::class
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // 可以在这里使用 Gate::define(...) 注册简单授权规则。
        //
    }
}
