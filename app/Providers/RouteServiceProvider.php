<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

// 学习要点：Laravel 10 中，路由加载和 API 限流规则集中在 RouteServiceProvider。
// 新骨架会把这些配置收敛到 bootstrap/app.php；10.x 则显式暴露这个 Provider，便于学习生命周期。
class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // 定义名为 api 的限流器。app/Http/Kernel.php 的 api 组使用 throttle:api 时会引用这里。
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // routes() 注册路由加载回调。框架启动 provider 时会执行这里，把路由文件载入 Router。
        $this->routes(function () {
            // routes/api.php 默认拥有 /api 前缀，并使用 api 中间件组。
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // routes/web.php 使用 web 中间件组，适合浏览器页面、session、cookie、CSRF。
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            if (config('interview_examples.enabled')) {
                Route::middleware('web')
                    ->group(base_path('routes/interview_examples.php'));
            }
        });
    }
}
