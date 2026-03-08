<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_positions', function (Blueprint $table) {
            $table->string('client_id', 64)->nullable()->after('liveset_id');

            // Add new unique constraint first (so FK can use it)
            $table->unique(['user_id', 'liveset_id', 'client_id']);
        });

        // Drop old unique constraint in separate statement (after new one exists)
        Schema::table('playback_positions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'liveset_id']);
        });
    }

    public function down(): void
    {
        Schema::table('playback_positions', function (Blueprint $table) {
            $table->unique(['user_id', 'liveset_id']);
        });

        Schema::table('playback_positions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'liveset_id', 'client_id']);
            $table->dropColumn('client_id');
        });
    }
};
