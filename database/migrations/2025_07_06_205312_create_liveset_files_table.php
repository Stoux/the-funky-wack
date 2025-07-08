<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void
    {
        Schema::create('liveset_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liveset_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('path');
            $table->string('quality');
            $table->boolean('original');
            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::dropIfExists('liveset_files');
    }

};
