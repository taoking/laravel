<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('metric_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 120)->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 80)->unique();
            $table->string('level', 30)->default('country')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('frequencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 40)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_category_id')->constrained('metric_categories');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('code', 120)->unique();
            $table->string('unit', 40)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['metric_category_id', 'status']);
            $table->index(['name', 'code']);
        });

        Schema::create('metric_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('frequency_id')->nullable()->constrained('frequencies')->nullOnDelete();
            $table->date('period_date');
            $table->string('period_label', 40);
            $table->decimal('value', 20, 4);
            $table->string('source', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['metric_id', 'region_id', 'frequency_id', 'period_date'], 'metric_values_unique_period');
            $table->index(['region_id', 'frequency_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_values');
        Schema::dropIfExists('metrics');
        Schema::dropIfExists('frequencies');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('metric_categories');
    }
};
