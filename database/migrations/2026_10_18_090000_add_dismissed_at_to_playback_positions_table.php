<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_positions', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('playback_positions', function (Blueprint $table) {
            $table->dropColumn('dismissed_at');
        });
    }
};
