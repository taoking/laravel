<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 学习要点：Migration 是数据库结构的版本控制。
// up() 描述如何应用变更，down() 描述如何回滚变更；团队协作时通过迁移同步 schema。
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // users 表是默认认证模型 App\Models\User 的数据来源。
        // Eloquent 约定 User 模型对应 users 表，主键为 id。
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // password_reset_tokens 存储密码重置 token。
        // email 作为主键表示同一邮箱同一时间只有一个有效重置记录。
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // sessions 表配合 config/session.php 中的 database driver 使用。
        // 生产环境使用数据库或 Redis 存储 session，便于多实例共享登录态。
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚会撤销 up() 创建的表。当前 user_id 只是索引字段，没有声明数据库外键约束。
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
