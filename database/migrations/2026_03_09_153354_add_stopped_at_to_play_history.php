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
        Schema::table('play_history', function (Blueprint $table) {
            $table->timestamp('stopped_at')->nullable()->after('counted_as_play');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('play_history', function (Blueprint $table) {
            $table->dropColumn('stopped_at');
        });
    }
};
