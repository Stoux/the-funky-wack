<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listen_room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listen_room_id')->constrained('listen_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 40);
            $table->string('client_id', 64)->nullable();
            $table->enum('role', ['host', 'listener']);
            $table->enum('mode', ['synced', 'independent']);
            $table->foreignId('play_history_id')->nullable()->constrained('play_history')->nullOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['listen_room_id', 'left_at']);
            $table->index(['session_id', 'left_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listen_room_members');
    }
};
