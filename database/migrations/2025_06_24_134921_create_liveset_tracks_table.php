<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up()
    {
        Schema::create('liveset_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liveset_id');
            $table->string('title');
            $table->integer('timestamp')->nullable();
            $table->unsignedInteger('order');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('liveset_tracks');
    }
};
