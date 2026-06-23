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
        Schema::create('acceleration_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('engine_type')->default('clickhouse')->index();
            $table->string('mode')->default('detail_table')->index();
            $table->string('status')->default('disabled')->index();
            $table->foreignId('source_connection_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->foreignId('target_connection_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->string('target_database')->nullable();
            $table->string('target_table');
            $table->string('refresh_type')->default('manual')->index();
            $table->unsignedInteger('refresh_interval_minutes')->nullable();
            $table->timestamp('last_refresh_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->index(['dataset_id', 'status']);
            $table->unique(['dataset_id', 'target_database', 'target_table'], 'acc_profiles_dataset_target_unique');
        });

        Schema::create('acceleration_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acceleration_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_field_id')->nullable()->constrained('dataset_fields')->nullOnDelete();
            $table->string('source_field_name');
            $table->string('target_field_name');
            $table->string('source_type')->default('unknown');
            $table->string('target_type')->default('String');
            $table->boolean('is_dimension')->default(false);
            $table->boolean('is_metric')->default(false);
            $table->json('aggregate_functions_json')->nullable();
            $table->boolean('is_partition_key')->default(false);
            $table->boolean('is_order_key')->default(false);
            $table->boolean('is_nullable')->default(true);
            $table->timestamps();

            $table->unique(['acceleration_profile_id', 'source_field_name'], 'acc_columns_profile_source_unique');
            $table->unique(['acceleration_profile_id', 'target_field_name'], 'acc_columns_profile_target_unique');
        });

        Schema::create('acceleration_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acceleration_profile_id')->constrained()->cascadeOnDelete();
            $table->string('task_type')->default('full_sync')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('source_row_count')->nullable();
            $table->unsignedBigInteger('target_row_count')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('logs_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['acceleration_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceleration_tasks');
        Schema::dropIfExists('acceleration_columns');
        Schema::dropIfExists('acceleration_profiles');
    }
};
