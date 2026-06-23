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
            $table->unsignedBigInteger('aggregate_definition_id')->nullable()->index();
            $table->string('aggregate_table')->nullable()->index();
            $table->boolean('detail_fallback_used')->default(false)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropIndex('query_logs_aggregate_definition_id_index');
            $table->dropIndex('query_logs_aggregate_table_index');
            $table->dropIndex('query_logs_detail_fallback_used_index');
        });

        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropColumn([
                'aggregate_definition_id',
                'aggregate_table',
                'detail_fallback_used',
            ]);
        });
    }
};
