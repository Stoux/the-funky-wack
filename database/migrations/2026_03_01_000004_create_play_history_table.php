<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 40)->nullable(); // Laravel session ID for anonymous dedup
            $table->foreignId('liveset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('started_at_position')->default(0);
            $table->unsignedInteger('ended_at_position')->nullable();
            $table->unsignedInteger('duration_listened')->default(0);
            $table->string('quality', 10)->nullable();
            $table->string('platform', 20)->default('web');
            $table->boolean('counted_as_play')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['liveset_id', 'counted_as_play']);
            $table->index(['session_id', 'liveset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_history');
    }
};
