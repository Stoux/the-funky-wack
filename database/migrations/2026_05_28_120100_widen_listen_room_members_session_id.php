<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same widening as play_history.session_id — listen_room_members stores
     * the resolved session id when a client joins a presence channel, so it
     * needs the same VARCHAR(64) ceiling to accept mobile X-Client-IDs.
     */
    public function up(): void
    {
        Schema::table('listen_room_members', function (Blueprint $table) {
            $table->string('session_id', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('listen_room_members', function (Blueprint $table) {
            $table->string('session_id', 40)->change();
        });
    }
};
