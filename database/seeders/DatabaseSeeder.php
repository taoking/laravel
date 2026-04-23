<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

// 学习要点：Seeder 用于给本地、测试或演示环境写入初始数据。
// 生产环境 seeder 要保持可重复执行，避免重复插入或破坏真实数据。
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 可以批量生成随机用户：
        // User::factory(10)->create();

        // 默认骨架创建一个固定测试用户，便于本地登录或调试认证流程。
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
