<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_mysql_demo_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_no')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->index();
            $table->unsignedInteger('amount_cents');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at', 'order_no'], 'idx_interview_orders_status_paid_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_mysql_demo_orders');
    }
};
