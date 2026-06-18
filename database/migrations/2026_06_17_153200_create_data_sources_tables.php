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
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('type')->default('mysql')->index();
            $table->string('host');
            $table->unsignedInteger('port')->default(3306);
            $table->string('database_name');
            $table->string('username');
            $table->text('password_encrypted')->nullable();
            $table->string('charset')->default('utf8mb4');
            $table->string('timezone')->default('+00:00');
            $table->json('options_json')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_tested_at')->nullable();
            $table->json('last_test_result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('data_source_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('table_name');
            $table->text('table_comment')->nullable();
            $table->string('table_type')->nullable();
            $table->unsignedBigInteger('row_count_estimate')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['data_source_id', 'table_name']);
        });

        Schema::create('data_source_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('data_source_tables')->cascadeOnDelete();
            $table->string('table_name');
            $table->string('field_name');
            $table->text('field_comment')->nullable();
            $table->string('data_type');
            $table->string('normalized_type')->index();
            $table->boolean('is_nullable')->default(false);
            $table->boolean('is_primary_key')->default(false);
            $table->text('default_value')->nullable();
            $table->unsignedInteger('ordinal_position');
            $table->timestamps();

            $table->unique(['data_source_id', 'table_name', 'field_name']);
            $table->index(['data_source_id', 'table_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_source_fields');
        Schema::dropIfExists('data_source_tables');
        Schema::dropIfExists('data_sources');
    }
};
