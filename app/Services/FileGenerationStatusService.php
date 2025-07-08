<?php

namespace App\Services;

use Psr\SimpleCache\CacheInterface;

class FileGenerationStatusService
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {

    }

    public function isConvertingFile(int $fileId): bool
    {
        return $this->cache->has($this->getCacheKey($fileId));
    }

    public function isGeneratingWaveform(int $livesetId): bool
    {
        return $this->cache->has($this->getCacheKey($livesetId, true));
    }

    public function setConvertingFile(int $fileId, bool $isConverting): void
    {
        $this->setOrDelete($this->getCacheKey($fileId), $isConverting);
    }

    public function setGeneratingWaveform(int $livesetId, bool $isGeneratingWaveform): void
    {
        $this->setOrDelete($this->getCacheKey($livesetId, true), $isGeneratingWaveform);
    }

    private function setOrDelete(string $key, bool $set): void
    {
        if ($set) {
            $this->cache->set($key, true);
        } else {
            $this->cache->delete($key);
        }
    }

    private function getCacheKey(int $id, bool $waveform = false): string
    {
        return 'TFW::generate::'.$id.($waveform ? '::waveform' : '');
    }
}
