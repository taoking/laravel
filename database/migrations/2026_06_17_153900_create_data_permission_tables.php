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
        Schema::create('resource_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('resource_type')->index();
            $table->unsignedBigInteger('resource_id')->index();
            $table->string('subject_type')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('permission_type')->index();
            $table->timestamps();

            $table->unique(['resource_type', 'resource_id', 'subject_type', 'subject_id', 'permission_type'], 'resource_permissions_unique');
        });

        Schema::create('data_permission_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('field_name');
            $table->string('operator');
            $table->string('value_type')->default('static');
            $table->json('value_json')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['dataset_id', 'status']);
        });

        Schema::create('column_permission_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('field_name');
            $table->string('permission_type')->index();
            $table->timestamps();

            $table->unique(['dataset_id', 'subject_type', 'subject_id', 'field_name', 'permission_type'], 'column_permissions_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_permission_rules');
        Schema::dropIfExists('data_permission_rules');
        Schema::dropIfExists('resource_permissions');
    }
};
