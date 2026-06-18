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
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('dataset_type')->default('single_table')->index();
            $table->string('main_table');
            $table->json('config_json')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dataset_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('table_name');
            $table->string('alias')->nullable();
            $table->string('join_type')->nullable();
            $table->text('join_condition')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['dataset_id', 'table_name']);
        });

        Schema::create('dataset_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('table_name');
            $table->string('field_name');
            $table->string('field_alias')->nullable();
            $table->string('display_name');
            $table->string('source_type')->default('physical');
            $table->string('normalized_type')->default('unknown')->index();
            $table->string('semantic_type')->default('normal')->index();
            $table->boolean('is_dimension')->default(false);
            $table->boolean('is_metric')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_filterable')->default(true);
            $table->string('default_aggregate')->default('none');
            $table->text('expression')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['dataset_id', 'table_name', 'field_name']);
            $table->index(['dataset_id', 'is_dimension']);
            $table->index(['dataset_id', 'is_metric']);
        });

        Schema::create('dataset_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('dataset_fields')->cascadeOnDelete();
            $table->string('operator');
            $table->string('value_type')->default('static');
            $table->json('value_json')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_filters');
        Schema::dropIfExists('dataset_fields');
        Schema::dropIfExists('dataset_tables');
        Schema::dropIfExists('datasets');
    }
};
