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
            $table->string('engine_type')->nullable()->index();
            $table->string('data_source_type')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropIndex('query_logs_engine_type_index');
            $table->dropIndex('query_logs_data_source_type_index');
        });

        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropColumn([
                'engine_type',
                'data_source_type',
            ]);
        });
    }
};
