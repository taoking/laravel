<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 80)->unique();
            $table->string('data_scope', 30)->default('all')->index();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('permissions')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 120)->unique();
            $table->string('type', 20)->index();
            $table->string('route_name')->nullable()->index();
            $table->string('uri')->nullable();
            $table->string('method', 12)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index(['type', 'sort_order']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->nullOnDelete();
            $table->string('title', 100);
            $table->string('route_name')->nullable()->index();
            $table->string('path', 160);
            $table->string('icon', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true)->index();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
