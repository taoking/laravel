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
        Schema::create('query_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dataset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('chart_id')->nullable()->index();
            $table->unsignedBigInteger('dashboard_id')->nullable()->index();
            $table->string('query_hash')->index();
            $table->longText('sql');
            $table->json('bindings_json')->nullable();
            $table->unsignedInteger('elapsed_ms')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->boolean('cached')->default(false);
            $table->boolean('is_slow')->default(false)->index();
            $table->string('status')->default('success')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_logs');
    }
};
