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
        Schema::create('acceleration_aggregate_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('detail_profile_id')->constrained('acceleration_profiles')->cascadeOnDelete();
            $table->foreignId('aggregate_profile_id')->nullable()->constrained('acceleration_profiles')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('disabled')->index();
            $table->string('target_database')->nullable();
            $table->string('target_table')->nullable();
            $table->string('time_field')->nullable();
            $table->string('time_grain')->default('none')->index();
            $table->json('dimensions_json')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->string('refresh_type')->default('manual')->index();
            $table->timestamp('last_refresh_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dataset_id', 'status']);
            $table->unique(['dataset_id', 'target_database', 'target_table'], 'acc_agg_defs_dataset_target_unique');
        });

        Schema::create('acceleration_aggregate_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aggregate_definition_id')->constrained('acceleration_aggregate_definitions')->cascadeOnDelete();
            $table->string('source_field_name')->nullable();
            $table->string('target_field_name');
            $table->string('column_role')->index();
            $table->string('aggregate_function')->default('none')->index();
            $table->string('source_type')->default('unknown');
            $table->string('target_type')->default('String');
            $table->timestamps();

            $table->unique(['aggregate_definition_id', 'target_field_name'], 'acc_agg_cols_def_target_unique');
            $table->index(['aggregate_definition_id', 'column_role'], 'acc_agg_cols_def_role_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceleration_aggregate_columns');
        Schema::dropIfExists('acceleration_aggregate_definitions');
    }
};
