<?php

use App\Models\Playlist;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Generate share codes for playlists that don't have one
        // and shorten existing 16-char codes to 8 chars
        Playlist::withTrashed()
            ->chunk(100, function ($playlists) {
                foreach ($playlists as $playlist) {
                    if (! $playlist->share_code) {
                        $playlist->update(['share_code' => Playlist::generateShareCode()]);
                    } elseif (strlen($playlist->share_code) > 8) {
                        // Shorten existing codes, regenerate if collision
                        $newCode = substr($playlist->share_code, 0, 8);
                        if (Playlist::withTrashed()->where('share_code', $newCode)->where('id', '!=', $playlist->id)->exists()) {
                            $newCode = Playlist::generateShareCode();
                        }
                        $playlist->update(['share_code' => $newCode]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse - codes have been regenerated
    }
};
