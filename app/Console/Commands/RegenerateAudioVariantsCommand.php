<?php

namespace App\Console\Commands;

use App\Enums\LivesetQuality;
use App\Jobs\ConvertAudioJob;
use App\Models\LivesetFile;
use App\Services\FileGenerationStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RegenerateAudioVariantsCommand extends Command
{
    protected $signature = 'livesets:regenerate-variants
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Delete non-original LQ/HQ files and queue new conversion jobs';

    public function handle(FileGenerationStatusService $statusService): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');

        // Find all lossless originals that exist on disk
        $losslessOriginals = LivesetFile::where('original', true)
            ->where('quality', LivesetQuality::LOSSLESS)
            ->with('liveset.files')
            ->get()
            ->filter(fn (LivesetFile $file) => $file->existsOnDisk());

        $this->info('Found '.$losslessOriginals->count().' lossless originals on disk.');

        $livesetIdsWithLossless = $losslessOriginals->pluck('liveset_id');

        // Find non-original LQ/HQ files only for livesets that have a lossless original
        $filesToDelete = LivesetFile::where('original', false)
            ->whereIn('quality', [LivesetQuality::LQ, LivesetQuality::HQ])
            ->whereIn('liveset_id', $livesetIdsWithLossless)
            ->with('liveset')
            ->get();

        $this->newLine();
        $this->info('Found '.$filesToDelete->count().' non-original LQ/HQ files to delete (with lossless backup).');

        foreach ($filesToDelete as $file) {
            $this->line("  - [{$file->liveset->id}] {$file->path}");
        }

        $this->newLine();
        $this->info('Will queue '.($losslessOriginals->count() * 2).' conversion jobs for '.$losslessOriginals->count().' livesets.');

        if (! $this->confirm($dryRun ? 'Show what would be generated?' : 'Proceed with deleting and regenerating?')) {
            $this->warn('Aborted.');

            return Command::SUCCESS;
        }

        // Delete old files
        if (! $dryRun && $filesToDelete->count() > 0) {
            foreach ($filesToDelete as $file) {
                $this->line("  Deleting {$file->path}");
                $disk->delete($file->path);
                $file->delete();
            }
            $this->info('Deleted '.$filesToDelete->count().' files.');
        }

        // Queue new jobs
        $jobsQueued = 0;

        foreach ($losslessOriginals as $original) {
            $liveset = $original->liveset;

            foreach ([LivesetQuality::LQ, LivesetQuality::HQ] as $quality) {
                // Build the new file path
                $newFilePath = preg_replace(
                    '/\.[a-z0-9]{2,4}$/i',
                    '.'.$quality->value.'.m4a',
                    $original->path,
                );

                $this->line("  - [{$liveset->id}] {$newFilePath}");

                if (! $dryRun) {
                    // Skip if file already exists
                    if ($disk->exists($newFilePath)) {
                        $this->warn('    ^ Skipped, file already exists');

                        continue;
                    }

                    // Create DB entry and queue job
                    $newFile = LivesetFile::create([
                        'path' => $newFilePath,
                        'quality' => $quality,
                        'original' => false,
                        'liveset_id' => $liveset->id,
                    ]);

                    $statusService->setConvertingFile($newFile->id, true);
                    ConvertAudioJob::dispatch($original, $newFile);
                    $jobsQueued++;
                }
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry run complete. No changes made.');
        } else {
            $this->info("Queued {$jobsQueued} conversion jobs.");
        }

        return Command::SUCCESS;
    }
}
