<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LivesetQuality;
use App\Http\Controllers\Controller;
use App\Jobs\ConvertAudioJob;
use App\Jobs\GenerateAudiowaveformJob;
use App\Models\Liveset;
use App\Models\LivesetFile;
use App\Services\CueSheetParser;
use App\Services\FileGenerationStatusService;
use App\Services\TrackLinker;
use App\Services\WavDurationReader;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LivesetFilesController extends Controller
{
    public function livesetFiles(FileGenerationStatusService $statusService, CueSheetParser $cueParser, WavDurationReader $durationReader, Liveset $liveset)
    {
        $disk = Storage::disk('public');

        return Inertia::render('Admin/LivesetFiles', [
            'liveset' => $liveset->load('edition'),
            'files' => $liveset->files->map(fn (LivesetFile $file) => [
                ...$file->toArray(),
                'exists' => $disk->exists($file->path),
                'converting' => $statusService->isConvertingFile($file->id),
            ]),
            'audioFiles' => $this->listFiles(Storage::disk('local'), 'audio'),
            'importSuggestions' => $this->importSuggestions($cueParser, $durationReader),
            'trackCount' => $liveset->tracks()->count(),
            'qualities' => LivesetQuality::options(),
            'isGeneratingWaveform' => $statusService->isGeneratingWaveform($liveset->id),
        ]);
    }

    /**
     * What each audio file (and the cue sheet next to it) can fill in on the liveset
     *
     * @return array<string, array{tracks: int, recorded_at: ?string, duration: ?int}>
     */
    protected function importSuggestions(CueSheetParser $cueParser, WavDurationReader $durationReader): array
    {
        $result = [];
        foreach ($this->listAudioFiles(Storage::disk('local'), 'audio') as $file) {
            $cue = $this->readCue($cueParser, 'audio/'.$file);
            $suggestion = [
                'tracks' => count($cue['tracks'] ?? []),
                'recorded_at' => ($cue['recorded_at'] ?? null)?->toIso8601String(),
                'duration' => $this->readDuration($durationReader, 'audio/'.$file),
            ];

            if ($suggestion['tracks'] > 0 || $suggestion['recorded_at'] !== null || $suggestion['duration'] !== null) {
                $result[$file] = $suggestion;
            }
        }

        return $result;
    }

    protected function readDuration(WavDurationReader $durationReader, string $audioPath): ?int
    {
        if (! str_ends_with(strtolower($audioPath), '.wav')) {
            return null;
        }

        $disk = Storage::disk('local');
        $stream = $disk->readStream($audioPath);
        if (! $stream) {
            return null;
        }

        try {
            return $durationReader->seconds($stream, $disk->size($audioPath));
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{performer: ?string, recorded_at: ?\Carbon\CarbonImmutable, tracks: list<array{timestamp: int, title: string}>}|null
     */
    protected function readCue(CueSheetParser $cueParser, string $audioPath): ?array
    {
        $disk = Storage::disk('local');
        $cuePath = $this->cuePath($audioPath);

        return $disk->exists($cuePath) ? $cueParser->parse($disk->get($cuePath)) : null;
    }

    protected function cuePath(string $audioPath): string
    {
        return preg_replace('/\.[^.\/]+$/', '.cue', $audioPath);
    }

    protected function listAudioFiles(Filesystem $disk, string $path)
    {
        $path = rtrim($path, '/').'/';

        $result = [];
        $files = $disk->files($path, true);
        foreach ($files as $file) {
            $file = substr($file, strlen($path));

            if (! preg_match('/\.(mp3|m4a|opus|mp4|webm|ogg|wav|flac)$/i', $file)) {
                continue;
            }

            $result[] = $file;
        }

        return $result;
    }

    protected function listFiles(Filesystem $disk, string $path): array
    {
        $map = [];
        $files = $this->listAudioFiles($disk, $path);
        foreach ($files as $file) {
            $filePath = explode('/', $file);
            $currentMap = &$map;
            foreach ($filePath as $index => $pathSection) {
                if ($index === count($filePath) - 1) {
                    $currentMap[$pathSection] = $file;
                } else {
                    $currentMap[$pathSection] ??= [];
                }
                $currentMap = &$currentMap[$pathSection];
            }
            unset($currentMap);
        }

        return $map;
    }

    public function importLivesetFile(CueSheetParser $cueParser, WavDurationReader $durationReader, TrackLinker $trackLinker, Liveset $liveset, Request $request)
    {
        $v = $request->validate([
            'path' => 'string|required',
            'name' => [
                'string',
                'regex:/^[a-zA-Z0-9\-_.]+$/',
            ],
            'quality' => [
                'required',
                'in:'.implode(',', array_keys(LivesetQuality::options())),
            ],
            'original' => 'boolean',
            'import_cue_tracks' => 'boolean',
            'import_cue_start' => 'boolean',
            'import_duration' => 'boolean',
        ]);

        $audioFiles = $this->listAudioFiles(Storage::disk('local'), 'audio');
        if (! in_array($v['path'], $audioFiles)) {
            throw ValidationException::withMessages([
                'path' => ['Unknown file to import'],
            ])->status(429);
        }

        // Create the public dir
        $privateDir = Storage::disk('local');
        $publicDir = Storage::disk('public');
        if (! $publicDir->exists('livesets/'.$liveset->id)) {
            $publicDir->makeDirectory('livesets/'.$liveset->id);
        }

        // Insert the file into the DB
        $toPath = 'livesets/'.$liveset->id.'/'.$v['name'];
        $livesetFile = LivesetFile::create([
            ...$v,
            'path' => $toPath,
            'liveset_id' => $liveset->id,
        ]);

        // Copy from private dir to public dir with our name
        $fromStream = $privateDir->readStream('audio/'.$v['path']);
        $written = $publicDir->put($toPath, $fromStream);
        if (! $written) {
            $livesetFile->delete();
            throw ValidationException::withMessages([
                'path' => ['Could not write file'],
            ])->status(429);
        }

        $filledIn = [];
        $cue = $this->readCue($cueParser, 'audio/'.$v['path']);
        if ($cue !== null && ($v['import_cue_tracks'] ?? false) && $cue['tracks'] !== []) {
            $this->replaceTracks($trackLinker, $liveset, $cue['tracks']);
            $filledIn[] = count($cue['tracks']).' tracks';
        }
        if ($cue !== null && ($v['import_cue_start'] ?? false) && $cue['recorded_at'] !== null) {
            $liveset->update(['started_at' => $cue['recorded_at']]);
            $filledIn[] = 'start time';
        }
        if ($v['import_duration'] ?? false) {
            $duration = $this->readDuration($durationReader, 'audio/'.$v['path']);
            if ($duration !== null) {
                $liveset->update(['duration_in_seconds' => $duration]);
                $filledIn[] = 'duration';
            }
        }

        return redirect()->route('admin.livesets.files', [$liveset])
            ->with('success', $filledIn !== []
                ? 'Liveset file imported, filled in '.Arr::join($filledIn, ', ', ' and ').'.'
                : 'Liveset file imported.');
    }

    /**
     * Replace the liveset's tracklist with the tracks of a cue sheet
     *
     * @param  list<array{timestamp: int, title: string}>  $tracks
     */
    protected function replaceTracks(TrackLinker $trackLinker, Liveset $liveset, array $tracks): void
    {
        DB::transaction(function () use ($liveset, $tracks) {
            $liveset->tracks()->delete();

            foreach ($tracks as $index => $track) {
                $liveset->tracks()->create([
                    'title' => $track['title'],
                    'timestamp' => $track['timestamp'],
                    'transition_start' => null,
                    'order' => $index + 1,
                ]);
            }
        });

        // Replaced lines leave their alias (and maybe a track) behind
        $trackLinker->pruneUnused();
    }

    public function editLivesetFile(Liveset $liveset, LivesetFile $file, Request $request)
    {
        $v = $request->validate([
            'quality' => [
                'required',
                'in:'.implode(',', array_keys(LivesetQuality::options())),
            ],
            'original' => 'boolean',
        ]);

        $file->update($v);

        return redirect()->route('admin.livesets.files', [$liveset])
            ->with('success', 'Liveset file updated.');
    }

    public function deleteLivesetFile(Liveset $liveset, LivesetFile $file)
    {
        Storage::disk('public')->delete($file->path);
        $file->delete();

        return redirect()->route('admin.livesets.files', [$liveset])
            ->with('success', 'Liveset file deleted.');
    }

    public function convertLivesetFile(FileGenerationStatusService $statusService, Liveset $liveset, LivesetFile $file, Request $request)
    {
        $validated = $request->validate([
            'quality' => [
                'required',
                'in:'.LivesetQuality::LQ->value.','.LivesetQuality::HQ->value,
            ],
        ]);

        // Ensure the original quality is lossless to prevent conversions of conversions
        $quality = LivesetQuality::from($validated['quality']);
        if ($file->quality !== LivesetQuality::LOSSLESS) {
            throw ValidationException::withMessages([
                'original' => ['File must be original & lossless'],
            ]);
        }

        // Build the new file path
        $newFilePath = preg_replace(
            '/\.[a-z0-9]{2,4}$/i',
            '.'.$quality->value.'.m4a',
            $file->path,
        );

        // Make sure that file doesn't exist yet
        Storage::disk('public')->exists($newFilePath) && throw ValidationException::withMessages([
            'path' => ['Target file '.$newFilePath.' already exists!?'],
        ]);

        // Insert the new entry
        $newFile = LivesetFile::create([
            'path' => $newFilePath,
            'quality' => $quality,
            'original' => false,
            'liveset_id' => $liveset->id,
        ]);

        // Convert the file
        $statusService->setConvertingFile($newFile->id, true);
        ConvertAudioJob::dispatch($file, $newFile);

        return redirect()->route('admin.livesets.files', [$liveset])
            ->with('success', 'Liveset file conversion started.');
    }

    public function generateAudiowaveform(FileGenerationStatusService $statusService, Liveset $liveset, Request $request)
    {
        // Make sure it doesn't already have one

        // Find the original file
        $file = $liveset->files->firstWhere('original', true);
        if (! $file) {
            throw ValidationException::withMessages([
                'path' => ['No original file found'],
            ])->status(429);
        }

        // Build the new file path
        $newFilePath = preg_replace(
            '/\.[a-z0-9]{2,4}$/i',
            '.json',
            $file->path,
        );

        // Make sure that file doesn't exist yet
        Storage::disk('public')->exists($newFilePath) && throw ValidationException::withMessages([
            'path' => ['Target file '.$newFilePath.' already exists!?'],
        ]);

        // Start conversion job
        $liveset->update([
            'audio_waveform_path' => $newFilePath,
        ]);
        $statusService->setGeneratingWaveform($liveset->id, true);
        GenerateAudiowaveformJob::dispatch($liveset, $file);

        return redirect()->route('admin.livesets.files', [$liveset])
            ->with('success', 'Liveset audio waveform generation started.');
    }

    public function deleteAudiowaveform(Liveset $liveset)
    {
        if ($liveset->audio_waveform_path) {
            Storage::disk('public')->delete($liveset->audio_waveform_path);
        }
        $liveset->update([
            'audio_waveform_path' => null,
        ]);

        return redirect()->route('admin.livesets.files', [$liveset])
            ->with('success', 'Liveset audio waveform deleted.');
    }
}
