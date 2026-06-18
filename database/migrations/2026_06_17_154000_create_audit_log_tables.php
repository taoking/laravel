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
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('resource_type')->nullable()->index();
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->string('request_method', 20);
            $table->text('request_url');
            $table->string('request_ip')->nullable();
            $table->text('request_user_agent')->nullable();
            $table->json('request_payload_json')->nullable();
            $table->unsignedInteger('response_code')->default(0)->index();
            $table->unsignedInteger('elapsed_ms')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('login_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status')->index();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('export_task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type')->index();
            $table->unsignedBigInteger('source_id')->index();
            $table->string('file_path')->nullable();
            $table->string('status')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('operation_logs');
    }
};
