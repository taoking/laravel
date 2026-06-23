<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acceleration_refresh_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('target_type')->index();
            $table->unsignedBigInteger('target_id')->index();
            $table->string('refresh_type')->default('manual')->index();
            $table->string('cron_expression')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->foreignId('last_task_id')->nullable()->constrained('acceleration_tasks')->nullOnDelete();
            $table->string('last_status')->nullable()->index();
            $table->text('last_error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['target_type', 'target_id', 'refresh_type'], 'acc_refresh_target_type_unique');
            $table->index(['enabled', 'next_run_at'], 'acc_refresh_enabled_next_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceleration_refresh_schedules');
    }
};
