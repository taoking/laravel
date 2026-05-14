<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// 学习要点：Feature Test 通常继承这个基类。
// 它会启动 Laravel 应用，因此可以使用 $this->get()、actingAs()、assertDatabaseHas() 等测试辅助方法。
abstract class TestCase extends BaseTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
