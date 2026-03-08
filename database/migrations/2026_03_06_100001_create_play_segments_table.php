<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_history_id')->constrained('play_history')->cascadeOnDelete();
            $table->unsignedInteger('start_position'); // seconds
            $table->unsignedInteger('end_position')->nullable(); // seconds
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['play_history_id', 'start_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_segments');
    }
};
