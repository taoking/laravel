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
            $table->string('request_source')->nullable()->after('dashboard_id')->index();
            $table->string('query_mode')->nullable()->after('request_source')->index();
            $table->string('permission_hash')->nullable()->after('logical_plan_hash')->index();
            $table->boolean('permission_applied')->default(false)->after('permission_hash')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropIndex('query_logs_request_source_index');
            $table->dropIndex('query_logs_query_mode_index');
            $table->dropIndex('query_logs_permission_hash_index');
            $table->dropIndex('query_logs_permission_applied_index');
        });

        Schema::table('query_logs', function (Blueprint $table) {
            $table->dropColumn([
                'request_source',
                'query_mode',
                'permission_hash',
                'permission_applied',
            ]);
        });
    }
};
