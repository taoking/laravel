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
        Schema::table('query_logs', function (Blueprint $table) {
            $table->boolean('semantic_layer_used')->default(false)->index();
            $table->json('semantic_metrics_json')->nullable();
            $table->json('semantic_dimensions_json')->nullable();
            $table->json('metric_versions_json')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropIndex('query_logs_semantic_layer_used_index');
        });

        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropColumn([
                'semantic_layer_used',
                'semantic_metrics_json',
                'semantic_dimensions_json',
                'metric_versions_json',
            ]);
        });
    }
};
