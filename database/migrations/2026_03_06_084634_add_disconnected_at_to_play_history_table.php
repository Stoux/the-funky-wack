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
            // Track when connection was lost - allows resuming session on reconnect
            $table->timestamp('disconnected_at')->nullable()->after('counted_as_play');
            $table->index(['session_id', 'disconnected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('play_history', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'disconnected_at']);
            $table->dropColumn('disconnected_at');
        });
    }
};
