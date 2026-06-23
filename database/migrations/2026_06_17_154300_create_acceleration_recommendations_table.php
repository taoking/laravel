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
        Schema::create('acceleration_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('chart_id')->nullable()->index();
            $table->unsignedBigInteger('dashboard_id')->nullable()->index();
            $table->string('recommendation_type')->default('aggregate_table')->index();
            $table->string('status')->default('pending')->index();
            $table->string('priority')->default('medium')->index();
            $table->text('reason');
            $table->json('dimensions_json')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->string('time_field')->nullable();
            $table->string('time_grain')->nullable();
            $table->unsignedInteger('estimated_query_count')->default(0);
            $table->unsignedInteger('estimated_avg_duration_ms')->nullable();
            $table->unsignedInteger('estimated_max_duration_ms')->nullable();
            $table->unsignedBigInteger('estimated_total_duration_ms')->nullable();
            $table->unsignedBigInteger('estimated_benefit_score')->default(0)->index();
            $table->json('source_query_log_ids_json')->nullable();
            $table->foreignId('created_profile_id')->nullable()->constrained('acceleration_profiles')->nullOnDelete();
            $table->foreignId('created_aggregate_definition_id')->nullable()->constrained('acceleration_aggregate_definitions')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['dataset_id', 'recommendation_type', 'status'], 'acc_recs_dataset_type_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceleration_recommendations');
    }
};
