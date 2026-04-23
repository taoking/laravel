<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

// 学习要点：维护模式中间件会在应用层拦截请求。
// public/index.php 也有更早的维护模式检查，两者共同保证维护期间请求不会进入业务逻辑。
class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
