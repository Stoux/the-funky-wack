<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen play_history.session_id from VARCHAR(40) to VARCHAR(64) so mobile
     * clients can use their X-Client-ID as a session fallback without
     * truncation. 64 matches devices.client_id, keeping the two columns
     * compatible for joins.
     */
    public function up(): void
    {
        Schema::table('play_history', function (Blueprint $table) {
            $table->string('session_id', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('play_history', function (Blueprint $table) {
            $table->string('session_id', 40)->nullable()->change();
        });
    }
};
