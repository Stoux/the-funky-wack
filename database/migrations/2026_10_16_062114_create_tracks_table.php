<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('artist');
            $table->string('title');
            $table->string('version')->nullable();
            $table->string('slug')->unique();
            // Normalized artist/title/version, used to link new tracklist lines to an existing track
            $table->string('match_key')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
