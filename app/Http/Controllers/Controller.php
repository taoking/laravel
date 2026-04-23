<?php

namespace App\Http\Controllers;

// 学习要点：所有应用控制器可以继承这个基类。
// 当前骨架保持它为空，是为了让应用按需添加共享能力，例如 authorize、通用响应格式或辅助方法。
// 不建议把业务逻辑放进基类；基类应只承载真正跨控制器复用的薄能力。
abstract class Controller
{
    //
}
