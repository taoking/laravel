<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// 学习要点：Feature Test 继承这个基类，因此可以使用 $this->get()、actingAs()、assertDatabaseHas() 等 Laravel 测试辅助方法。
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
