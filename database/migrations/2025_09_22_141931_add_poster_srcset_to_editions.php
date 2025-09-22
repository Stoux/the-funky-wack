<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration{
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->json('poster_srcset')->nullable()->after('poster_path');
        });
    }

    public function down(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->dropColumn('poster_srcset');
        });
    }
};
