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
        Schema::create('import_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 20);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('progress')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_task_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_task_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('status')->index();
            $table->text('message')->nullable();
            $table->json('raw_data_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('uploaded_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('import_task_id')->constrained()->cascadeOnDelete();
            $table->string('table_name')->unique();
            $table->string('display_name');
            $table->json('schema_json');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploaded_tables');
        Schema::dropIfExists('import_task_logs');
        Schema::dropIfExists('import_tasks');
    }
};
