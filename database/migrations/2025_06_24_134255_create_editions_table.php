<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up()
    {
        Schema::create('editions', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('tag_line');
            $table->date('date');
            $table->string('poster_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('editions');
    }
};
