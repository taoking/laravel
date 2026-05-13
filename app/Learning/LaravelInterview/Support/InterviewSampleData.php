<?php

namespace App\Learning\LaravelInterview\Support;

final class InterviewSampleData
{
    /**
     * @return array{items: array<int, array{name: string, quantity: int, price_cents: int}>, total_cents: int}
     */
    public static function cart(): array
    {
        return [
            'items' => [
                ['name' => 'Laravel 面试手册', 'quantity' => 1, 'price_cents' => 9900],
                ['name' => '队列与缓存练习题', 'quantity' => 2, 'price_cents' => 2900],
            ],
            'total_cents' => 15700,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function postPayload(): array
    {
        return [
            'title' => 'laravel interview example',
            'slug' => 'laravel-interview-example',
            'excerpt' => '覆盖路由、验证、Eloquent、队列、事件、缓存、容器等常见考点。',
            'body' => '这是一份用于 git diff 阅读的 Laravel 组件样例代码。',
            'is_published' => true,
            'published_at' => now()->toDateTimeString(),
        ];
    }
}
