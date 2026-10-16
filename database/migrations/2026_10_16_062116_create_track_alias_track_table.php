<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A line links to one track, or several for a mashup
        Schema::create('track_alias_track', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_alias_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['track_alias_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_alias_track');
    }
};
