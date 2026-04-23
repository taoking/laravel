<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// 学习要点：Unit Test 不启动完整 Laravel 应用，速度更快。
// 适合测试纯业务类、值对象、算法和不依赖框架容器的逻辑。
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
