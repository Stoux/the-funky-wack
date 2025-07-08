<?php

namespace App\Jobs;

use App\Models\Liveset;
use App\Models\LivesetFile;
use App\Services\FileGenerationStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAudiowaveformJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Liveset $liveset,
        private readonly LivesetFile $original,
    ) {
        $this->queue = 'long-running-queue';
    }

    public function handle(
        FileGenerationStatusService $statusService,
    ): void
    {
        try {
            $this->convert();
        } catch (\Throwable $e) {
            Log::error('Failed to convert, deleting path from liveset', [
                'path' => $this->liveset->audio_waveform_path,
                'e' => $e,
            ]);

            $this->liveset->update([
                'audio_waveform_path' => null,
            ]);

            throw $e;
        } finally {
            $statusService->setGeneratingWaveform($this->liveset->id, false);;
        }
    }

    protected function convert(): void
    {
        Log::info('Generate audiowaveform', [
            'original' => $this->original->path,
            'target' => $this->liveset->audio_waveform_path,
        ]);;

        $disk = \Storage::disk('public');

        if (!$disk->exists($this->original->path)) {
            throw new \Exception('Original file does not exist? ' . $this->original->path);
        }
        if ($disk->exists($this->liveset->audio_waveform_path)) {
            throw new \Exception('Target file already exists? ' . $this->liveset->audio_waveform_path);
        }

        $originalPath = $disk->path($this->original->path);
        $targetPath = $disk->path($this->liveset->audio_waveform_path);

        $audiowaveform = config('app.tfw.audiowaveform');
        Log::info('Make sure audiowaveform exists', [
            'path' => $audiowaveform,
        ]);
        if (!\Process::run([$audiowaveform, '-v'])->successful()) {
            throw new \Exception('audiowaveform not found / could not be run?');
        }

        $convertProcess = \Process::run([$audiowaveform, '-i', $originalPath, '-o', $targetPath, '--pixels-per-second', '30', '--bits', '8'], function (string $type, string $output) {
            if ($type === 'err') {
                Log::warning($output);
            } else {
                Log::info($output);
            }
        });

        if (!$convertProcess->successful()) {
            throw new \RuntimeException('Failed to generate audiowaveform: ' . $convertProcess->exitCode() . ': ' . $this->liveset->audio_waveform_path);
        }

        Log::info('Generated audiowaveform!');


    }

}
