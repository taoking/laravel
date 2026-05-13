<?php

use App\Learning\LaravelInterview\Http\Controllers\InterviewExamplesController;
use App\Learning\LaravelInterview\Http\Middleware\EnsureInterviewToken;
use Illuminate\Support\Facades\Route;

// 本文件默认未在 web.php 中加载，适合通过 git diff 阅读。
// 如需本地体验，可在 routes/web.php 中按需添加：
// if (config('interview_examples.enabled')) { require __DIR__.'/interview_examples.php'; }

// 假设本文件从 routes/web.php 引入，因此外层已经拥有 web 中间件组。
Route::prefix('interview-examples')
    ->name('interview.')
    ->group(function (): void {
        Route::get('/', [InterviewExamplesController::class, 'index'])->name('index');
        Route::get('/collections', [InterviewExamplesController::class, 'collections'])->name('collections');
        Route::get('/dashboard-stats', [InterviewExamplesController::class, 'dashboardStats'])->name('dashboard-stats');

        // 隐式路由模型绑定：{post} 会解析为 Post 模型实例。
        Route::get('/posts/{post}', [InterviewExamplesController::class, 'show'])->name('posts.show');

        // 自定义 FormRequest + 自定义 Middleware。
        Route::post('/posts', [InterviewExamplesController::class, 'store'])
            ->middleware(EnsureInterviewToken::class)
            ->name('posts.store');

        // auth 认证、限流、自定义 token、服务容器接口绑定会在这个示例中一起出现。
        Route::post('/checkout', [InterviewExamplesController::class, 'checkout'])
            ->middleware(['auth', 'throttle:interview-api', EnsureInterviewToken::class])
            ->name('checkout');

        Route::get('/throttled', fn () => response()->json(['ok' => true]))
            ->middleware('throttle:interview-api')
            ->name('throttled');
    });
