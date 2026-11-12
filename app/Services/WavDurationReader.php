<?php

namespace App\Services;

class WavDurationReader
{
    /**
     * The chunks before the audio data (fmt, JUNK, LIST, ...) are tiny, the data chunk is always in here
     */
    protected const HEADER_BYTES = 65536;

    /**
     * Read the duration of a WAV file from its header, without touching the audio itself
     *
     * @param  resource  $stream
     * @return int|null Seconds, null when it isn't a WAV file we understand
     */
    public function seconds($stream, int $fileSize): ?int
    {
        $header = fread($stream, self::HEADER_BYTES);
        if ($header === false || strlen($header) < 12
            || ! in_array(substr($header, 0, 4), ['RIFF', 'RF64'], true)
            || substr($header, 8, 4) !== 'WAVE') {
            return null;
        }

        $byteRate = null;
        $offset = 12;
        while ($offset + 8 <= strlen($header)) {
            $chunkId = substr($header, $offset, 4);
            $chunkSize = unpack('V', substr($header, $offset + 4, 4))[1];

            if ($chunkId === 'fmt ' && $offset + 20 <= strlen($header)) {
                $byteRate = unpack('V', substr($header, $offset + 16, 4))[1];
            } elseif ($chunkId === 'data') {
                if (! $byteRate) {
                    return null;
                }

                // Interrupted recordings (and RF64) don't have a trustworthy size, the file itself does
                $dataSize = min($chunkSize, $fileSize - $offset - 8);

                return (int) round($dataSize / $byteRate);
            }

            // Chunks are padded to an even size
            $offset += 8 + $chunkSize + ($chunkSize % 2);
        }

        return null;
    }
}
