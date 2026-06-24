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
        Schema::create('metadata_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_type', 64);
            $table->unsignedBigInteger('asset_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->foreignId('dataset_id')->nullable()->constrained('datasets')->nullOnDelete();
            $table->string('status', 32)->default('active');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tags_json')->nullable();
            $table->json('properties_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_type', 'asset_id']);
            $table->index(['asset_type', 'status']);
            $table->index('data_source_id');
            $table->index('dataset_id');
            $table->index('owner_id');
        });

        Schema::create('metadata_lineage_relations', function (Blueprint $table) {
            $table->id();
            $table->string('source_asset_type', 64);
            $table->unsignedBigInteger('source_asset_id');
            $table->string('target_asset_type', 64);
            $table->unsignedBigInteger('target_asset_id');
            $table->string('relation_type', 64);
            $table->json('relation_detail_json')->nullable();
            $table->string('confidence', 32)->default('high');
            $table->boolean('created_by_system')->default(true);
            $table->timestamps();

            $table->unique([
                'source_asset_type',
                'source_asset_id',
                'target_asset_type',
                'target_asset_id',
                'relation_type',
            ], 'metadata_lineage_unique');
            $table->index(['source_asset_type', 'source_asset_id'], 'metadata_lineage_source_index');
            $table->index(['target_asset_type', 'target_asset_id'], 'metadata_lineage_target_index');
            $table->index('relation_type');
        });

        Schema::create('metadata_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('metadata_asset_tags', function (Blueprint $table) {
            $table->id();
            $table->string('asset_type', 64);
            $table->unsignedBigInteger('asset_id');
            $table->foreignId('tag_id')->constrained('metadata_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asset_type', 'asset_id', 'tag_id'], 'metadata_asset_tags_unique');
            $table->index(['asset_type', 'asset_id']);
        });

        Schema::create('metadata_usage_stats', function (Blueprint $table) {
            $table->id();
            $table->string('asset_type', 64);
            $table->unsignedBigInteger('asset_id');
            $table->date('usage_date');
            $table->unsignedInteger('query_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('edit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('avg_duration_ms')->nullable();
            $table->unsignedInteger('slow_query_count')->default(0);
            $table->timestamps();

            $table->unique(['asset_type', 'asset_id', 'usage_date'], 'metadata_usage_unique');
            $table->index(['asset_type', 'asset_id']);
            $table->index('usage_date');
            $table->index('last_used_at');
        });

        Schema::create('impact_analysis_logs', function (Blueprint $table) {
            $table->id();
            $table->string('asset_type', 64);
            $table->unsignedBigInteger('asset_id');
            $table->string('change_type', 64);
            $table->json('impact_result_json');
            $table->string('risk_level', 32);
            $table->foreignId('analyzed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_type', 'asset_id']);
            $table->index('change_type');
            $table->index('risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impact_analysis_logs');
        Schema::dropIfExists('metadata_usage_stats');
        Schema::dropIfExists('metadata_asset_tags');
        Schema::dropIfExists('metadata_tags');
        Schema::dropIfExists('metadata_lineage_relations');
        Schema::dropIfExists('metadata_assets');
    }
};
