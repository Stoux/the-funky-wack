<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LivesetQuality;
use App\Http\Controllers\Controller;
use App\Jobs\ConvertAudioJob;
use App\Jobs\GenerateAudiowaveformJob;
use App\Models\Liveset;
use App\Models\LivesetFile;
use App\Services\FileGenerationStatusService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LivesetFilesController extends Controller
{
    public function livesetFiles(FileGenerationStatusService $statusService, Liveset $liveset)
    {
        $disk = Storage::disk('public');

        return Inertia::render('Admin/LivesetFiles', [
            'liveset' => $liveset->load('edition'),
            'files' => $liveset->files->map(fn(LivesetFile $file) => [
                ...$file->toArray(),
                'exists' => $disk->exists($file->path),
                'converting' => $statusService->isConvertingFile($file->id),
            ]),
            'audioFiles' => $this->listFiles(Storage::disk('local'), 'audio'),
            'qualities' => LivesetQuality::options(),
            'isGeneratingWaveform' => $statusService->isGeneratingWaveform($liveset->id),
        ]);
    }

    protected function listAudioFiles(Filesystem $disk, string $path)
    {
        $path = rtrim( $path, '/' ) . '/';

        $result = [];
        $files = $disk->files($path, true);
        foreach( $files as $file ) {
            $file = substr( $file, strlen( $path ) );

            if (!preg_match('/\.(mp3|m4a|opus|mp4|webm|ogg|wav|flac)$/i', $file)) {
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
        foreach( $files as $file ) {
            $filePath = explode('/', $file);
            $currentMap = &$map;
            foreach( $filePath as $index => $pathSection ) {
                if( $index === count( $filePath ) - 1 ) {
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

    public function importLivesetFile(Liveset $liveset, Request $request)
    {
        $v = $request->validate([
            'path' => 'string|required',
            'name' => [
                'string',
                'regex:/^[a-zA-Z0-9\-_.]+$/',
            ],
            'quality' => [
                'required',
                'in:' . implode(',', array_keys( LivesetQuality::options() ) ),
            ],
            'original' => 'boolean',
        ]);

        $audioFiles = $this->listAudioFiles(Storage::disk('local'), 'audio');
        if (! in_array($v['path'], $audioFiles)) {
            throw ValidationException::withMessages([
                'path' => ["Unknown file to import"],
            ])->status(429);
        }

        // Create the public dir
        $privateDir = Storage::disk('local');
        $publicDir = Storage::disk('public');
        if (!$publicDir->exists('livesets/' . $liveset->id)) {
            $publicDir->makeDirectory('livesets/' . $liveset->id);
        }

        // Insert the file into the DB
        $toPath = 'livesets/' . $liveset->id . '/' . $v['name'];
        $livesetFile = LivesetFile::create([
            ...$v,
            'path' => $toPath,
            'liveset_id' => $liveset->id,
        ]);

        // Copy from private dir to public dir with our name
        $fromStream = $privateDir->readStream('audio/' . $v['path']);
        $written = $publicDir->put($toPath, $fromStream);
        if (!$written) {
            $livesetFile->delete();
            throw ValidationException::withMessages([
                'path' => ["Could not write file"],
            ])->status(429);
        }

        return redirect()->route('admin.livesets.files', [ $liveset ])
            ->with('success', 'Liveset file imported.');
    }

    public function editLivesetFile(Liveset $liveset, LivesetFile $file, Request $request)
    {
        $v = $request->validate([
            'quality' => [
                'required',
                'in:' . implode(',', array_keys( LivesetQuality::options() ) ),
            ],
            'original' => 'boolean',
        ]);

        $file->update($v);

        return redirect()->route('admin.livesets.files', [ $liveset ])
            ->with('success', 'Liveset file updated.');
    }

    public function deleteLivesetFile(Liveset $liveset, LivesetFile $file)
    {
        Storage::disk('public')->delete($file->path);
        $file->delete();

        return redirect()->route('admin.livesets.files', [ $liveset ])
            ->with('success', 'Liveset file deleted.');
    }

    public function convertLivesetFile(FileGenerationStatusService $statusService, Liveset $liveset, LivesetFile $file, Request $request)
    {
        $validated = $request->validate([
            'quality' => [
                'required',
                'in:' . LivesetQuality::LQ->value . ',' . LivesetQuality::HQ->value,
            ],
        ]);

        // Ensure the original quality is lossless to prevent conversions of conversions
        $quality = LivesetQuality::from($validated['quality']);
        if ($file->quality !== LivesetQuality::LOSSLESS) {
            throw ValidationException::withMessages([
                'original' => ["File must be original & lossless"],
            ]);
        }

        // Build the new file path
        $newFilePath = preg_replace(
            '/\.[a-z0-9]{2,4}$/i',
            '.' . $quality->value . '.opus',
            $file->path,
        );

        // Make sure that file doesn't exist yet
        Storage::disk('public')->exists($newFilePath) && throw ValidationException::withMessages([
            'path' => [ 'Target file ' . $newFilePath . ' already exists!?' ],
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

        return redirect()->route('admin.livesets.files', [ $liveset ])
            ->with('success', 'Liveset file conversion started.');
    }

    public function generateAudiowaveform(FileGenerationStatusService $statusService, Liveset $liveset, Request $request)
    {
        // Make sure it doesn't already have one


        // Find the original file
        $file = $liveset->files->firstWhere('original', true);
        if (!$file) {
            throw ValidationException::withMessages([
                'path' => ["No original file found"],
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
            'path' => [ 'Target file ' . $newFilePath . ' already exists!?' ],
        ]);


        // Start conversion job
        $liveset->update([
            'audio_waveform_path' => $newFilePath,
        ]);
        $statusService->setGeneratingWaveform($liveset->id, true);
        GenerateAudiowaveformJob::dispatch($liveset, $file);


        return redirect()->route('admin.livesets.files', [ $liveset ])
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

        return redirect()->route('admin.livesets.files', [ $liveset ])
            ->with('success', 'Liveset audio waveform deleted.');
    }


}
