<?php

namespace App\Http\Controllers;

use App\Enums\LivesetQuality;
use App\Models\Edition;
use App\Models\Liveset;
use App\Models\LivesetFile;
use App\Models\LivesetTrack;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ListController extends Controller
{

    public function index()
    {
        $editions = Edition::all()->map(fn($edition) => [
            ...$edition->toArray(),
            'date' => $edition->date?->format('j M Y'),
            'livesets' => $edition->livesets->sortBy('lineup_order')->map(fn(Liveset $liveset) => [
                ...$liveset->toArray(),
                'started_at' => $liveset->started_at?->format('j M Y H:i'),
                'tracks' => $liveset->tracks->sortBy('order')->map(fn(LivesetTrack $track) => [
                    ...$track->toArray(),
                ]),
                'audio_waveform_url' => $liveset->audioWaveformUrl(),
                'files' => $liveset->files->filter(fn(LivesetFile $file) => $file->existsOnDisk())->mapWithKeys(fn(LivesetFile $file) => [
                    $file->quality->value => \Storage::disk('public')->url($file->path),
                ])->toArray() ?: null,
            ])->values(),
        ]);

        return Inertia::render('List', [
            'editions' => $editions,
            'qualities' => LivesetQuality::options(),
        ]);
    }

}
