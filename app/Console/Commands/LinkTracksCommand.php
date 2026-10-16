<?php

namespace App\Console\Commands;

use App\Enums\TrackAliasStatus;
use App\Models\LivesetTrack;
use App\Models\TrackAlias;
use App\Services\TrackLinker;
use Illuminate\Console\Command;

class LinkTracksCommand extends Command
{
    protected $signature = 'tracks:link';

    protected $description = 'Link every tracklist line that has no track alias yet (backfill, safe to run again)';

    public function handle(TrackLinker $linker): int
    {
        $titles = LivesetTrack::query()
            ->whereNotIn('title', TrackAlias::query()->select('text'))
            ->distinct()
            ->pluck('title');

        $this->withProgressBar($titles, fn (string $title) => $linker->link($title));
        $this->newLine();

        $this->info("Linked {$titles->count()} tracklist lines.");
        $this->table(['Status', 'Lines'], collect(TrackAliasStatus::cases())->map(fn (TrackAliasStatus $status) => [
            $status->value,
            TrackAlias::where('status', $status)->count(),
        ]));

        return self::SUCCESS;
    }
}
