<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_aliases', function (Blueprint $table) {
            $table->id();
            // Exact tracklist line, liveset_tracks are recreated on every save so they're linked by their text
            $table->string('text')->unique();
            $table->string('status')->default('auto')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_aliases');
    }
};
