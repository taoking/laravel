<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
// 学习要点：Factory 用于生成测试或 seeder 数据。
// User 模型使用 HasFactory 后，可以通过 User::factory() 创建用户。
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // 静态缓存 password hash，避免批量创建用户时重复计算同一个哈希。
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        // state() 返回一个派生状态，便于测试未验证邮箱用户。
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
