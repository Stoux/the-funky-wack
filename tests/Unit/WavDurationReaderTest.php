<?php

use App\Services\WavDurationReader;

/**
 * A WAV file like rekordbox writes them: RIFF, a JUNK chunk to reserve room for RF64, fmt, then the audio data
 */
function wavFile(int $seconds, int $byteRate = 8000, ?int $claimedDataSize = null): string
{
    $data = str_repeat("\x80", $seconds * $byteRate);
    $fmt = pack('vvVVvv', 1, 1, $byteRate, $byteRate, 1, 8);

    $chunks = 'JUNK'.pack('V', 28).str_repeat("\0", 28)
        .'fmt '.pack('V', strlen($fmt)).$fmt
        .'data'.pack('V', $claimedDataSize ?? strlen($data)).$data;

    return 'RIFF'.pack('V', 4 + strlen($chunks)).'WAVE'.$chunks;
}

function wavSeconds(string $contents): ?int
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    return (new WavDurationReader)->seconds($stream, strlen($contents));
}

it('reads the duration from the header', function () {
    expect(wavSeconds(wavFile(3)))->toBe(3);
});

it('trusts the file size over a broken data chunk size', function () {
    expect(wavSeconds(wavFile(2, claimedDataSize: 0xFFFFFFFF)))->toBe(2);
});

it('returns null for anything that is not a WAV', function (string $contents) {
    expect(wavSeconds($contents))->toBeNull();
})->with([
    'empty' => [''],
    'mp3' => ["ID3\x04\0\0\0\0\0\0"],
    'no fmt chunk' => ['RIFF'.pack('V', 12).'WAVEdata'.pack('V', 0)],
]);
