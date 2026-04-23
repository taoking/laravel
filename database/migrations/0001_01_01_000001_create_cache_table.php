<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 学习要点：当前骨架默认 cache store 是 database，因此需要 cache 相关表。
// 如果生产环境改用 Redis/Memcached，这些表可以不作为主要缓存路径。
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // cache 表保存普通缓存值。key 是缓存键，value 是序列化后的内容，expiration 是过期时间戳。
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        // cache_locks 支持原子锁能力，例如 Cache::lock(...)。
        // 它常用于防止重复执行定时任务、报表生成或并发扣减等操作。
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
