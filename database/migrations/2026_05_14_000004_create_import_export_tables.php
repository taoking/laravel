<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->string('original_name');
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_task_id')->constrained('import_tasks')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('payload')->nullable();
            $table->json('errors');
            $table->timestamps();

            $table->index(['import_task_id', 'row_number']);
        });

        Schema::create('export_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->string('type', 40)->default('metrics');
            $table->string('status', 30)->default('pending')->index();
            $table->json('filters')->nullable();
            $table->string('disk', 40)->default('local');
            $table->string('path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_tasks');
        Schema::dropIfExists('import_failures');
        Schema::dropIfExists('import_tasks');
    }
};
