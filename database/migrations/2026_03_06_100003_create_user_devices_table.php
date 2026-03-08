<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 64);
            $table->string('device_type', 20); // mobile, desktop, tablet, car, other
            $table->string('device_name');     // auto-detected on creation, immutable
            $table->string('device_nickname')->nullable(); // user-editable
            $table->text('user_agent')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'client_id']);
            $table->index(['user_id', 'is_hidden', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
