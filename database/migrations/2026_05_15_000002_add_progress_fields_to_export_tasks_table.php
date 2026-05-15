<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_tasks', function (Blueprint $table): void {
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('failure_type', 40)->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('export_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'total_rows',
                'processed_rows',
                'file_size',
                'attempts',
                'failure_type',
                'last_failed_at',
                'downloaded_at',
            ]);
        });
    }
};
