<?php

use App\Services\CueSheetParser;

function cueSheet(array $tracks, bool $rekordbox = true): string
{
    $lines = [];
    if ($rekordbox) {
        $lines[] = 'REM DATE 2026-11-12 09:30 PM';
        $lines[] = 'REM RECORDED_BY "rekordbox-dj"';
    }
    $lines[] = 'TITLE "REC-2026-11-12"';
    $lines[] = 'PERFORMER "Stambak"';
    $lines[] = 'FILE "01 REC-2026-11-12.wav" WAVE';

    foreach ($tracks as $number => [$performer, $title, $index]) {
        $lines[] = sprintf("\tTRACK %02d AUDIO", $number + 1);
        $lines[] = "\t\tTITLE \"{$title}\"";
        if ($performer !== null) {
            $lines[] = "\t\tPERFORMER \"{$performer}\"";
        }
        $lines[] = "\t\tFILE \"/Users/someone/Music/track.mp3\" WAVE";
        $lines[] = "\t\tINDEX 01 {$index}";
    }

    return implode("\r\n", $lines)."\r\n";
}

function cueTracks(string $contents): array
{
    return (new CueSheetParser)->parse($contents)['tracks'];
}

it('reads the performer and when the recording started', function () {
    $cue = (new CueSheetParser)->parse(cueSheet([]));

    expect($cue['performer'])->toBe('Stambak')
        ->and($cue['recorded_at']->format('Y-m-d H:i:s'))->toBe('2026-11-12 21:30:00');
});

it('has no start time when the cue has no date', function () {
    expect((new CueSheetParser)->parse(cueSheet([], rekordbox: false))['recorded_at'])->toBeNull();
});

it('parses rekordbox indexes as hh:mm:ss and joins performer and title', function () {
    expect(cueTracks(cueSheet([
        ['Bubble Love, Ross From Friends', 'Love', '00:00:00'],
        ['Antwood', 'Club Dread', '00:01:55'],
        ['Someone', 'Late One', '01:02:03'],
    ])))->toBe([
        ['timestamp' => 0, 'title' => 'Bubble Love, Ross From Friends - Love'],
        ['timestamp' => 115, 'title' => 'Antwood - Club Dread'],
        ['timestamp' => 3723, 'title' => 'Someone - Late One'],
    ]);
});

it('parses standard indexes as mm:ss:ff', function () {
    expect(cueTracks(cueSheet([
        ['A', 'One', '00:00:00'],
        ['B', 'Two', '75:30:74'],
    ], rekordbox: false)))->toBe([
        ['timestamp' => 0, 'title' => 'A - One'],
        ['timestamp' => 4530, 'title' => 'B - Two'],
    ]);
});

it('uses the title alone when there is no performer', function () {
    expect(cueTracks(cueSheet([
        [null, 'Killroy & Koarse - Rijksoverheid v20', '00:00:00'],
    ])))->toBe([
        ['timestamp' => 0, 'title' => 'Killroy & Koarse - Rijksoverheid v20'],
    ]);
});

it('cleans up download junk in titles', function (?string $performer, string $title, string $expected) {
    expect(cueTracks(cueSheet([[$performer, $title, '00:00:00']]))[0]['title'])->toBe($expected);
})->with([
    'beatport id' => ['Antwood', 'Club Dread [12345678]', 'Antwood - Club Dread'],
    'performer repeated in title' => ['Antwood', 'Antwood - Club Dread', 'Antwood - Club Dread'],
    'dash in the actual title' => ['Shaggy', "It Wasn't Me - SSOL Edit", "Shaggy - It Wasn't Me - SSOL Edit"],
    'file name' => [null, 'some_track_name-1234567', 'some track name'],
    'dashed file name' => [null, 'some-track-name', 'some track name'],
]);

it('drops tracks that were only flicked through and merges the repeats', function () {
    $tracks = cueTracks(cueSheet([
        ['G Jones', 'On The Platform', '00:24:47'],
        [null, 'Killroy & Koarse - Rijksoverheid v20', '00:26:01'],
        ['G Jones', 'On The Platform', '00:26:02'],
        [null, 'Killroy & Koarse - Rijksoverheid v20', '00:26:24'],
        ['Buunshin', 'CANCELLED', '00:27:31'],
        ['Gyrofield', 'Maybes', '00:39:14'],
        [null, 'Killroy & Koarse - Rijksoverheid v20', '00:40:40'],
        ['Gyrofield', 'Maybes', '00:40:42'],
        [null, 'Killroy & Koarse - Rijksoverheid v20', '00:40:51'],
    ]));

    expect(array_column($tracks, 'title', 'timestamp'))->toBe([
        1487 => 'G Jones - On The Platform',
        1584 => 'Killroy & Koarse - Rijksoverheid v20',
        1651 => 'Buunshin - CANCELLED',
        2354 => 'Gyrofield - Maybes',
        2451 => 'Killroy & Koarse - Rijksoverheid v20',
    ]);
});

it('returns no tracks for a cue sheet without tracks', function () {
    $contents = "REM DATE 2026-02-13 09:56 PM\r\nREM RECORDED_BY \"rekordbox-dj\"\r\nTITLE \"REC-2026-02-13\"\r\nPERFORMER \"Stambak\"\r\nFILE \"01 REC-2026-02-13.wav\" WAVE\r\n";

    $cue = (new CueSheetParser)->parse($contents);

    expect($cue['tracks'])->toBe([])
        ->and($cue['recorded_at'])->not->toBeNull();
});

it('handles a BOM and Windows-1252 encoded cue sheets', function () {
    $contents = "\xEF\xBB\xBF".cueSheet([['Beyonc'."\xE9", 'Halo', '00:00:00']]);

    expect(cueTracks($contents))->toBe([
        ['timestamp' => 0, 'title' => 'Beyoncé - Halo'],
    ]);
});

it('formats tracks as tracklist text lines', function () {
    expect((new CueSheetParser)->toTracklistLines([
        ['timestamp' => 115, 'title' => 'Antwood - Club Dread'],
        ['timestamp' => 3723, 'title' => 'Someone - Late One'],
    ]))->toBe([
        '00:01:55 | Antwood - Club Dread',
        '01:02:03 | Someone - Late One',
    ]);
});
