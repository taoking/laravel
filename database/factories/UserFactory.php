<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
// 学习要点：Factory 用于生成测试或填充数据库所需的模型数据。
// 它和 HasFactory trait 配合后，可以通过 User::factory() 创建用户。
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
            // 使用静态缓存避免每创建一个用户都重新 hash 一次相同密码，测试和 seeder 会更快。
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        // state() 返回一个新的 factory 状态，不会修改默认 definition。
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
