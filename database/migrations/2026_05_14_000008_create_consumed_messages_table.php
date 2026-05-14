<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumed_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 80)->index();
            $table->string('idempotency_key', 180);
            $table->string('consumer_group', 120);
            $table->string('topic', 160)->index();
            $table->unsignedInteger('partition')->default(0);
            $table->unsignedBigInteger('offset')->default(0);
            $table->string('event_type', 120)->index();
            $table->string('status', 30)->default('processing')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['consumer_group', 'idempotency_key'], 'consumed_messages_group_idempotency_unique');
            $table->index(['consumer_group', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumed_messages');
    }
};
