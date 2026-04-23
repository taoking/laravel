<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 学习要点：Feature Test 会启动 Laravel 应用，适合测试路由、中间件、认证和数据库交互。
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // 请求 routes/web.php 中定义的根路径。
        $response = $this->get('/');

        // 断言页面可以成功返回。
        $response->assertStatus(200);
    }
}
