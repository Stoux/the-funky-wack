<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Throwable;

class CueSheetParser
{
    /**
     * Tracks shorter than this were only flicked through on a deck, not actually played
     */
    public const MIN_PLAYED_SECONDS = 15;

    /**
     * Parse a cue sheet into its performer, the moment the recording started and its tracklist lines.
     *
     * Rekordbox writes its INDEX as hh:mm:ss instead of the standard mm:ss:ff (frames).
     *
     * @return array{performer: ?string, recorded_at: ?CarbonImmutable, tracks: list<array{timestamp: int, title: string}>}
     */
    public function parse(string $contents): array
    {
        $contents = $this->toUtf8($contents);
        $isRekordbox = (bool) preg_match('/^\s*REM\s+RECORDED_BY\s+"?rekordbox/mi', $contents);

        $performer = null;
        $recordedAt = null;
        $tracks = [];
        $current = null;
        foreach (preg_split('/\R/u', $contents) as $line) {
            $line = trim($line);

            if (preg_match('/^TRACK\s+\d+/i', $line)) {
                $current = ['title' => '', 'performer' => ''];

                continue;
            }

            if ($current === null) {
                if (preg_match('/^REM\s+DATE\s+(.+)$/i', $line, $matches)) {
                    $recordedAt = $this->parseDate($this->unquote($matches[1]));
                } elseif (preg_match('/^PERFORMER\s+(.+)$/i', $line, $matches)) {
                    $performer = $this->unquote($matches[1]) ?: null;
                }

                continue;
            }

            if (preg_match('/^(TITLE|PERFORMER)\s+(.*)$/i', $line, $matches)) {
                $current[strtolower($matches[1])] = $this->unquote($matches[2]);
            } elseif (preg_match('/^INDEX\s+01\s+(\d+):(\d{2}):(\d{2})$/i', $line, $matches)) {
                [, $first, $second, $third] = array_map('intval', $matches);
                $timestamp = $isRekordbox
                    ? $first * 3600 + $second * 60 + $third
                    : $first * 60 + $second;

                $title = $this->formatTitle($current['performer'], $current['title']);
                if ($title !== '') {
                    $tracks[] = ['timestamp' => $timestamp, 'title' => $title];
                }
                $current = null;
            }
        }

        return [
            'performer' => $performer,
            'recorded_at' => $recordedAt,
            'tracks' => $this->dropFlicks($tracks),
        ];
    }

    /**
     * The tracks as lines of the admin's tracklist text format
     *
     * @param  list<array{timestamp: int, title: string}>  $tracks
     * @return list<string>
     */
    public function toTracklistLines(array $tracks): array
    {
        return array_map(
            fn (array $track) => sprintf(
                '%02d:%02d:%02d | %s',
                intdiv($track['timestamp'], 3600),
                intdiv($track['timestamp'] % 3600, 60),
                $track['timestamp'] % 60,
                $track['title'],
            ),
            $tracks,
        );
    }

    protected function formatTitle(string $performer, string $title): string
    {
        $title = $this->cleanTitle($title);

        if ($performer === '') {
            return $title;
        }

        // Some files already have the artist in their title as well
        if (str_starts_with(mb_strtolower($title), mb_strtolower($performer).' - ')) {
            $title = mb_substr($title, mb_strlen($performer) + 3);
        }

        return $title === '' ? $performer : $performer.' - '.$title;
    }

    /**
     * Strip the junk that download sites leave in file based titles
     */
    protected function cleanTitle(string $title): string
    {
        // Beatport & co IDs: "Title [12345678]"
        $title = preg_replace('/\s*\[\d{8,15}\]\s*$/', '', $title);

        // Titles taken from file names: "some_track_name-1234567"
        if (! str_contains($title, ' - ')) {
            $title = str_replace('_', ' ', $title);
            $title = preg_replace('/-\s*\d{6,11}\s*$/', '', $title);
            if (substr_count($title, '-') >= 2) {
                $title = str_replace('-', ' ', $title);
            }
            $title = preg_replace('/[- ]{2,}/', ' ', $title);
        }

        return trim($title);
    }

    /**
     * Drop tracks that were barely audible, then merge the repeats that leaves behind
     *
     * @param  list<array{timestamp: int, title: string}>  $tracks
     * @return list<array{timestamp: int, title: string}>
     */
    protected function dropFlicks(array $tracks): array
    {
        $result = [];
        foreach ($tracks as $index => $track) {
            $next = $tracks[$index + 1] ?? null;
            if ($next !== null && $next['timestamp'] - $track['timestamp'] < self::MIN_PLAYED_SECONDS) {
                continue;
            }

            if ($result !== [] && end($result)['title'] === $track['title']) {
                continue;
            }

            $result[] = $track;
        }

        return $result;
    }

    /**
     * Rekordbox writes the local time the recording started: "2026-11-12 09:30 PM"
     */
    protected function parseDate(string $date): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($date)->startOfMinute();
        } catch (Throwable) {
            return null;
        }
    }

    protected function unquote(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^"(.*)"$/u', $value, $matches)) {
            $value = $matches[1];
        }

        return trim($value);
    }

    protected function toUtf8(string $contents): string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return $contents;
    }
}
