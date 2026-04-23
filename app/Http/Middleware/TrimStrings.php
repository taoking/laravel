<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

// 学习要点：TrimStrings 是全局中间件，会自动 trim 输入字符串。
// 密码类字段不 trim，避免用户有意输入的首尾空格被改变。
class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
