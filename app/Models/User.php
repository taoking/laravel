<?php

namespace App\Models;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\PermissionService;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// 学习要点：User 是默认认证模型，继承 Authenticatable 后具备认证相关契约能力。
// Eloquent 默认会把 App\Models\User 映射到 users 表，主键默认为 id，并自动维护 created_at / updated_at。
#[Fillable(['name', 'email', 'password', 'status', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    // HasFactory 让测试和 seeder 可以使用 User::factory() 构造数据。
    // Notifiable 让模型可以接收邮件、数据库、广播等通知。
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()
            ->where('code', 'super_admin')
            ->exists();
    }

    public function hasPermission(string $code): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($code, app(PermissionService::class)->permissionCodes($this), true);
    }

    public function permissionCodes(): array
    {
        if ($this->isSuperAdmin()) {
            return ['*'];
        }

        return app(PermissionService::class)->permissionCodes($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // datetime cast 会把字段转换为 Carbon 日期对象，便于格式化、比较和时区处理。
            'email_verified_at' => 'datetime',

            // hashed cast 会在赋值 password 时自动哈希；读取时不会反解。
            // 面试重点：不要在代码里存明文密码，也不要重复 hash 已经 hash 过的值。
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }
}
