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
            $table->string('logical_plan_hash')->nullable()->after('query_hash')->index();
            $table->unsignedInteger('raw_duration_ms')->nullable()->after('fallback_reason');
            $table->unsignedInteger('total_duration_ms')->nullable()->after('accelerated_duration_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropIndex('query_logs_logical_plan_hash_index');
            $table->dropColumn([
                'logical_plan_hash',
                'raw_duration_ms',
                'total_duration_ms',
            ]);
        });
    }
};
