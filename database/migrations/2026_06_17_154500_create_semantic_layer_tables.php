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
        Schema::create('metric_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('metric_categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('metric_categories')->nullOnDelete();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('metric_type')->default('base')->index();
            $table->string('aggregate_function')->default('sum')->index();
            $table->string('source_field')->nullable();
            $table->text('formula')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('precision')->default(2);
            $table->string('format_type')->default('number');
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['dataset_id', 'status']);
            $table->index(['dataset_id', 'code']);
        });

        Schema::create('metric_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('metric_type');
            $table->string('aggregate_function');
            $table->string('source_field')->nullable();
            $table->text('formula')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('precision')->default(2);
            $table->string('format_type')->default('number');
            $table->string('status');
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['metric_id', 'version']);
        });

        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('field_name');
            $table->string('dimension_type')->default('string')->index();
            $table->json('time_grain_options_json')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['dataset_id', 'code']);
            $table->index(['dataset_id', 'status']);
        });

        Schema::create('metric_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->foreignId('depends_on_metric_id')->nullable()->constrained('metrics')->cascadeOnDelete();
            $table->string('depends_on_field_name')->nullable();
            $table->string('dependency_type')->index();
            $table->timestamps();

            $table->index(['metric_id', 'dependency_type']);
            $table->index('depends_on_field_name');
        });

        Schema::create('metric_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained('metrics')->cascadeOnDelete();
            $table->unsignedInteger('metric_version')->nullable();
            $table->string('used_by_type');
            $table->unsignedBigInteger('used_by_id');
            $table->string('usage_context')->nullable();
            $table->timestamps();

            $table->unique(['metric_id', 'used_by_type', 'used_by_id', 'usage_context'], 'metric_usages_unique');
            $table->index(['used_by_type', 'used_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_usages');
        Schema::dropIfExists('metric_dependencies');
        Schema::dropIfExists('dimensions');
        Schema::dropIfExists('metric_versions');
        Schema::dropIfExists('metrics');
        Schema::dropIfExists('metric_categories');
    }
};
