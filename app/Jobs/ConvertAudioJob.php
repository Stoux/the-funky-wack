<?php

namespace App\Jobs;

use App\Enums\LivesetQuality;
use App\Models\LivesetFile;
use App\Services\FileGenerationStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConvertAudioJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly LivesetFile $original,
        private readonly LivesetFile $target,
    )
    {
        $this->queue = 'long-running-queue';
    }

    public function handle(FileGenerationStatusService $statusService): void
    {
        try {
            $this->convert();
        } catch( \Throwable $e ) {
            Log::error('Failed to convert, deleting db entry', [
                'target' => $this->target->id,
                'path' => $this->target->path,
                'e' => $e,
            ]);
            $this->target->delete();

            throw $e;
        } finally {
            $statusService->setConvertingFile($this->target->id, false);;
        }
    }

    protected function convert(): void
    {
        Log::info('Converting audio', [
            'original' => $this->original->path,
            'target' => $this->target->path,
            'quality' => $this->target->quality->label(),
        ]);;

        $disk = \Storage::disk('public');

        if (!$disk->exists($this->original->path)) {
            throw new \Exception('Original file does not exist? ' . $this->original->path);
        }
        if ($disk->exists($this->target->path)) {
            throw new \Exception('Target file already exists? ' . $this->target->path);
        }

        $originalPath = $disk->path($this->original->path);
        $targetPath = $disk->path($this->target->path);

        $ffmpeg = config('app.tfw.ffmepg');
        Log::info('Make sure ffmpeg exists', [
            'path' => $ffmpeg,
        ]);
        if (!\Process::run([$ffmpeg, '-h'])->successful()) {
            throw new \Exception('ffmpeg not found / could not be run?');
        }

        $qualityParam = match($this->target->quality) {
            LivesetQuality::HQ => '160k',
            LivesetQuality::LQ => '96k',
            default => throw new \Exception('Unsupported target quality: ' . $this->target->quality->value),
        };

        $convertProcess = \Process::run([$ffmpeg, '-i', $originalPath, '-c:a', 'libopus', '-b:a', $qualityParam, $targetPath], function (string $type, string $output) {
            if ($type === 'err') {
                Log::error($output);
            } else {
                Log::info($output);
            }
        });

        if (!$convertProcess->successful()) {
            throw new \RuntimeException('Failed to convert audio: ' . $convertProcess->exitCode() . ': ' . $this->target->path);
        }

        Log::info('Converted file!');
    }

}
