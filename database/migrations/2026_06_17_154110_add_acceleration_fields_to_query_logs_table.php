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
            $table->boolean('acceleration_hit')->default(false)->index();
            $table->unsignedBigInteger('acceleration_profile_id')->nullable()->index();
            $table->string('acceleration_engine')->nullable()->index();
            $table->string('acceleration_mode')->nullable()->index();
            $table->boolean('fallback_used')->default(false)->index();
            $table->text('fallback_reason')->nullable();
            $table->unsignedInteger('source_duration_ms')->nullable();
            $table->unsignedInteger('accelerated_duration_ms')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropIndex('query_logs_acceleration_hit_index');
            $table->dropIndex('query_logs_acceleration_profile_id_index');
            $table->dropIndex('query_logs_acceleration_engine_index');
            $table->dropIndex('query_logs_acceleration_mode_index');
            $table->dropIndex('query_logs_fallback_used_index');
        });

        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropColumn([
                'acceleration_hit',
                'acceleration_profile_id',
                'acceleration_engine',
                'acceleration_mode',
                'fallback_used',
                'fallback_reason',
                'source_duration_ms',
                'accelerated_duration_ms',
            ]);
        });
    }
};
