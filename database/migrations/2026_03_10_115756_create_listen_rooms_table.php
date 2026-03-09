<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listen_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('channel_token', 32)->unique();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('ended_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listen_rooms');
    }
};
