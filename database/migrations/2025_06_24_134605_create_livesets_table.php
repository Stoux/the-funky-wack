<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up()
    {
        Schema::create('livesets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id');

            $table->string('title');
            $table->string('artist_name');

            $table->text('description')->nullable();
            $table->string('bpm')->nullable()->comment('BPM indication of this liveset');
            $table->string('genre')->nullable();

            $table->integer('duration_in_seconds')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->unsignedSmallInteger('lineup_order')->nullable();

            // URL to the Soundcloud hosted version
            $table->string('soundcloud_url')->nullable();
            // Path to generated waveform
            $table->string('audio_waveform_path')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('livesets');
    }
};
