<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportLivesetsCommand extends Command
{
    protected $signature = 'edition:import-livesets {path* : path to audio file or folder with audio & cue files} {--edition= : Optional edition ID (otherwise prompts)}';

    protected $description = 'Import audio files';

    public function handle()
    {
        // TODO
        $paths = $this->argument('paths'); // Will be an array
        $edition = $this->option('edition');

        if (empty($paths)) {
            // This case should ideally not happen if you expect at least one path,
            // but it's a good defensive check if you use `*` without a specific minimum.
            $this->error('At least one path is required.');
            return Command::FAILURE;
        }

        // Validate edition if provided
        if ($edition !== null) {
            if (!is_numeric($edition) || (int)$edition != $edition) {
                $this->error("The 'edition' option must be an integer if provided.");
                return Command::FAILURE;
            }
            $edition = (int)$edition; // Cast to integer
            $this->info("Processing with edition: {$edition}");
        }

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                $this->warn("Path not found, skipping: {$path}");
                continue; // Skip to the next path
            }

            $this->info("--- Processing: {$path} ---");

            if (File::isDirectory($path)) {
                $this->info("This is a directory.");
                // Add your directory processing logic here
            } elseif (File::isFile($path)) {
                $this->info("This is a file.");
                // Add your file processing logic here
            } else {
                $this->warn("Path '{$path}' exists but is neither a file nor a directory (e.g., a symlink to non-existent target).");
            }
            // You can use $edition within your processing logic for each path
            // e.g., $this->info("Applying edition {$edition} to {$path}");
        }

        $this->info('All specified paths have been processed.');

        return Command::SUCCESS;

    }
}
