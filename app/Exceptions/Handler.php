<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

// 学习要点：Exception Handler 负责异常上报和渲染。
// bootstrap/app.php 把 Illuminate\Contracts\Debug\ExceptionHandler 绑定到这个类。
class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        // 验证失败时这些敏感字段不会被闪存回 session，避免密码类数据出现在旧输入中。
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // reportable 用于注册异常上报回调，例如接入日志平台或按异常类型过滤。
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
