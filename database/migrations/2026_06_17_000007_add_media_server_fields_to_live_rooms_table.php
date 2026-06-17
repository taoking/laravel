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
        Schema::table('live_rooms', function (Blueprint $table) {
            $table->string('playback_type')->default('video')->index();
            $table->string('stream_key')->nullable()->index();
            $table->string('push_url')->nullable();
            $table->string('playback_url')->nullable();
            $table->string('playback_protocol')->nullable();
            $table->string('media_server')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_rooms', function (Blueprint $table) {
            $table->dropColumn([
                'playback_type',
                'stream_key',
                'push_url',
                'playback_url',
                'playback_protocol',
                'media_server',
            ]);
        });
    }
};
