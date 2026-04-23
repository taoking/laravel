<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

// 学习要点：CSRF 防护中间件属于 web 中间件组。
// 表单请求需要携带有效 token；API 路由通常不使用 web 组，因此不走这里。
class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
