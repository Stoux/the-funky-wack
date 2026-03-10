<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('liveset_tracks', function (Blueprint $table) {
            $table->unsignedInteger('transition_start')->nullable()->after('timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('liveset_tracks', function (Blueprint $table) {
            $table->dropColumn('transition_start');
        });
    }
};
