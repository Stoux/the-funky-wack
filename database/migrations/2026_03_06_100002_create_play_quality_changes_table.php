<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_quality_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_history_id')->constrained('play_history')->cascadeOnDelete();
            $table->unsignedInteger('position'); // seconds where change occurred
            $table->string('quality', 10); // hq, lossless, lq
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('play_history_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_quality_changes');
    }
};
