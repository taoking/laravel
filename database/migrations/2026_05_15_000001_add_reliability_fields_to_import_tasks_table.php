<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_tasks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempts')->default(0)->after('failed_rows');
            $table->string('failure_type', 40)->nullable()->after('error_message');
            $table->timestamp('last_failed_at')->nullable()->after('failure_type');
            $table->timestamp('compensated_at')->nullable()->after('finished_at');
            $table->string('compensation_reason')->nullable()->after('compensated_at');
            $table->index(['status', 'last_failed_at'], 'import_tasks_status_last_failed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('import_tasks', function (Blueprint $table): void {
            $table->dropIndex('import_tasks_status_last_failed_at_index');
            $table->dropColumn([
                'attempts',
                'failure_type',
                'last_failed_at',
                'compensated_at',
                'compensation_reason',
            ]);
        });
    }
};
