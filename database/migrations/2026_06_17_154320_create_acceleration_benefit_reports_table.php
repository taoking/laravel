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
        Schema::create('acceleration_benefit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('chart_id')->nullable()->index();
            $table->unsignedBigInteger('dashboard_id')->nullable()->index();
            $table->foreignId('acceleration_profile_id')->nullable()->constrained('acceleration_profiles')->nullOnDelete();
            $table->foreignId('aggregate_definition_id')->nullable()->constrained('acceleration_aggregate_definitions')->nullOnDelete();
            $table->date('report_date')->index();
            $table->unsignedInteger('query_count')->default(0);
            $table->unsignedInteger('raw_query_count')->default(0);
            $table->unsignedInteger('detail_hit_count')->default(0);
            $table->unsignedInteger('aggregate_hit_count')->default(0);
            $table->unsignedInteger('cache_hit_count')->default(0);
            $table->unsignedInteger('fallback_count')->default(0);
            $table->unsignedInteger('avg_raw_duration_ms')->nullable();
            $table->unsignedInteger('avg_detail_duration_ms')->nullable();
            $table->unsignedInteger('avg_aggregate_duration_ms')->nullable();
            $table->unsignedInteger('avg_cache_duration_ms')->nullable();
            $table->bigInteger('estimated_saved_ms')->nullable();
            $table->timestamps();

            $table->unique(['report_date', 'dataset_id', 'chart_id', 'dashboard_id'], 'acc_benefit_scope_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceleration_benefit_reports');
    }
};
