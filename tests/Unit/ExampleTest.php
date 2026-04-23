<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// 学习要点：Unit Test 不启动完整 Laravel 应用，适合测试纯业务逻辑和值对象。
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
