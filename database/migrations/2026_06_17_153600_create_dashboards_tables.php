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
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('layout_json')->nullable();
            $table->json('global_filters_json')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_id')->constrained()->cascadeOnDelete();
            $table->string('widget_type')->default('chart');
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->unsignedInteger('w')->default(6);
            $table->unsignedInteger('h')->default(4);
            $table->json('config_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dashboard_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('field_name');
            $table->string('label');
            $table->string('filter_type')->default('select');
            $table->json('default_value_json')->nullable();
            $table->json('config_json')->nullable();
            $table->timestamps();
        });

        Schema::create('dashboard_linkages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_widget_id')->constrained('dashboard_widgets')->cascadeOnDelete();
            $table->foreignId('target_widget_id')->constrained('dashboard_widgets')->cascadeOnDelete();
            $table->string('source_field');
            $table->string('target_field');
            $table->json('config_json')->nullable();
            $table->timestamps();
        });

        Schema::create('dashboard_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('share_token')->unique();
            $table->string('share_type')->default('public')->index();
            $table->string('password_hash')->nullable();
            $table->timestamp('expired_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_shares');
        Schema::dropIfExists('dashboard_linkages');
        Schema::dropIfExists('dashboard_filters');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('dashboards');
    }
};
