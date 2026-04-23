<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

// 学习要点：Seeder 用于写入本地、测试或演示环境的初始数据。
// 生产环境 seeder 要注意幂等，避免重复插入或破坏真实数据。
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 可以使用 Factory 批量创建测试用户。
        // \App\Models\User::factory(10)->create();

        // 也可以创建固定用户，方便本地调试登录流程。
        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
