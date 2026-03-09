<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listen_room_members', function (Blueprint $table) {
            $table->timestamp('sync_paused_at')->nullable()->after('left_at');
        });
    }

    public function down(): void
    {
        Schema::table('listen_room_members', function (Blueprint $table) {
            $table->dropColumn('sync_paused_at');
        });
    }
};
