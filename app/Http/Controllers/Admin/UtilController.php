<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UtilController extends Controller
{
    public function parseCue(Request $request)
    {
        $validated = $request->validate([
            'content' => 'string',
        ]);

        $content = explode( "\r\n", $validated['content'] );

        $top_lines = [];
        $songs = [];
        $current_song_index = -1;

        foreach($content as $line) {
            if (preg_match('/^REM DATE (.+)$/', $line, $matches)) {
                $top_lines['recorded_at'] = $matches[1];
            } else if (preg_match('/^PERFORMER "(.+)"$/', $line, $matches)) {
                $top_lines['performer'] = $matches[1];
            } else if (preg_match('/\tTRACK \d+/', $line)) {
                // New track line. Bump the song index
                $current_song_index++;
            } else if (preg_match('/\t\tTITLE "(.+)"/i', $line, $matches)) {
                // Found the title of a track
                $title = $matches[1];
                if ( preg_match( '/^(.+?) - (.+)$/', $title, $matches ) ) {
                    $title = $matches[2];
                    $title = preg_replace( '/\s\[\d{8,15}\]\s*$/', '', $title );

                    $songs[$current_song_index]['title'] = $title;
                    $songs[$current_song_index]['artist'] = $matches[1];
                } else {
                    $title = str_replace('_', ' ', $title);
                    if ( preg_match('/^(.+)-\s*\d{6,11}\s*$/', $title, $matches ) ) {
                        $title = $matches[1];
                    }
                    if (strlen(preg_replace('/[^-]/', '', $title)) >= 2) {
                        $title = str_replace('-', ' ', $title);
                    }

                    $title = preg_replace('/[- ]{2,}/', ' ', $title);
                    $title = preg_replace( '/\s\[\d{8,15}\]\s*$/', '', $title );

                    $songs[$current_song_index]['title'] = $title;
                }
            }  else if (preg_match('/\t\tPERFORMER "(.+)"/i', $line, $matches)) {
                $songs[$current_song_index]['artist'] = $matches[1];
            } else if (preg_match('/\t\tINDEX\s\d+\s(\d{2}:\d{2}:\d{2})$/', $line, $matches)) {
                $songs[$current_song_index]['time'] = $matches[1];
            }
        }

        $song_lines = collect($songs)->map(fn($song) => sprintf("%s | %s - %s", $song['time'] ?? 'XX:XX:XX', $song['artist'] ?? '?', $song['title'] ?? '?',));

        return response()->json([
            ...$top_lines,
            'songs' => $song_lines->values(),
        ]);
    }
}
