<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

// 学习要点：Laravel 10 的控制器基类默认混入授权和验证能力。
// 业务控制器继承它后，可以使用 authorize()、validate() 等便捷方法。
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
