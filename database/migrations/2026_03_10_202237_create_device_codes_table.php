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
        Schema::create('device_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('client_id', 64);
            $table->string('device_name', 255);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->text('encrypted_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index(['code', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_codes');
    }
};
