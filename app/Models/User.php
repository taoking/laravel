<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// 学习要点：User 是默认认证模型，继承 Authenticatable 后具备登录认证相关能力。
// Eloquent 默认将 App\Models\User 映射到 users 表，主键为 id。
class User extends Authenticatable
{
    // HasApiTokens 来自 Sanctum，提供 API token 能力；HasFactory 用于测试数据；Notifiable 用于通知。
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // fillable 控制批量赋值白名单，防止用户提交未预期字段造成 mass assignment 风险。
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // hidden 控制模型序列化为数组或 JSON 时隐藏敏感字段。
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // datetime cast 会把字段转换为 Carbon 对象。
        'email_verified_at' => 'datetime',
        // hashed cast 会在设置 password 时自动哈希，避免保存明文密码。
        'password' => 'hashed',
    ];
}
