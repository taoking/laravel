<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 学习要点：Feature Test 会启动 Laravel 应用并模拟一次真实请求。
// 适合覆盖路由、中间件、认证、数据库和响应内容等端到端行为。
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // 这里请求 routes/web.php 中定义的根路径 `/`。
        $response = $this->get('/');

        // 断言 HTTP 状态码为 200，说明路由匹配、视图渲染和响应发送成功。
        $response->assertStatus(200);
    }
}
