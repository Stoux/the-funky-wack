<?php

use App\Models\Liveset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

function rekordboxCue(): string
{
    return implode("\r\n", [
        'REM DATE 2026-11-12 09:30 PM',
        'REM RECORDED_BY "rekordbox-dj"',
        'TITLE "REC-2026-11-12"',
        'PERFORMER "Stambak"',
        'FILE "01 REC.wav" WAVE',
        "\tTRACK 01 AUDIO",
        "\t\tTITLE \"Love\"",
        "\t\tPERFORMER \"Bubble Love\"",
        "\t\tINDEX 01 00:00:00",
        "\tTRACK 02 AUDIO",
        "\t\tTITLE \"Club Dread\"",
        "\t\tPERFORMER \"Antwood\"",
        "\t\tINDEX 01 00:01:55",
    ])."\r\n";
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->liveset = Liveset::factory()->create();

    $fmt = pack('vvVVvv', 1, 1, 8000, 8000, 1, 8);
    $data = str_repeat("\x80", 8000 * 3);
    $wav = 'RIFF'.pack('V', 4 + 8 + strlen($fmt) + 8 + strlen($data)).'WAVE'
        .'fmt '.pack('V', strlen($fmt)).$fmt
        .'data'.pack('V', strlen($data)).$data;

    Storage::disk('local')->put('audio/TFW15/Stambak/01 REC.wav', $wav);
    Storage::disk('local')->put('audio/TFW15/Stambak/01 REC.cue', rekordboxCue());
    Storage::disk('local')->put('audio/TFW15/Other/01 REC.mp3', 'ID3');
    Storage::disk('local')->put('audio/brncl-dark.wav', $wav);
});

function asCueAdmin($test)
{
    return $test->withSession(['admin_authenticated_at' => now()])->actingAs($test->admin);
}

function importCueAsAdmin($test, array $data)
{
    return asCueAdmin($test)->post(route('admin.livesets.files.import', $test->liveset), [
        'path' => 'TFW15/Stambak/01 REC.wav',
        'name' => 'stambak.wav',
        'quality' => 'lossless',
        'original' => true,
        ...$data,
    ]);
}

it('shows what each audio file and its cue can fill in', function () {
    asCueAdmin($this)->get(route('admin.livesets.files', $this->liveset))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('importSuggestions', [
                'TFW15/Stambak/01 REC.wav' => ['tracks' => 2, 'recorded_at' => '2026-11-12T21:30:00+01:00', 'duration' => 3],
                'brncl-dark.wav' => ['tracks' => 0, 'recorded_at' => null, 'duration' => 3],
            ])
            ->where('trackCount', 0));
});

it('imports the tracklist, start time and duration alongside the file', function () {
    importCueAsAdmin($this, ['import_cue_tracks' => true, 'import_cue_start' => true, 'import_duration' => true])
        ->assertRedirect(route('admin.livesets.files', $this->liveset))
        ->assertSessionHas('success', 'Liveset file imported, filled in 2 tracks, start time and duration.');

    expect($this->liveset->fresh()->duration_in_seconds)->toBe(3);

    Storage::disk('public')->assertExists("livesets/{$this->liveset->id}/stambak.wav");

    $tracks = $this->liveset->tracks()->orderBy('order')->get();
    expect($tracks->map->only('title', 'timestamp', 'transition_start', 'order')->all())->toBe([
        ['title' => 'Bubble Love - Love', 'timestamp' => 0, 'transition_start' => null, 'order' => 1],
        ['title' => 'Antwood - Club Dread', 'timestamp' => 115, 'transition_start' => null, 'order' => 2],
    ]);
    expect($this->liveset->fresh()->started_at->format('Y-m-d H:i'))->toBe('2026-11-12 21:30');
});

it('replaces an existing tracklist and start time', function () {
    $this->liveset->update(['started_at' => '2026-11-12 20:00:00']);
    $this->liveset->tracks()->create(['title' => 'Old - Track', 'timestamp' => 0, 'order' => 1]);

    importCueAsAdmin($this, ['import_cue_tracks' => true, 'import_cue_start' => true]);

    expect($this->liveset->tracks()->pluck('title')->all())->toBe(['Bubble Love - Love', 'Antwood - Club Dread'])
        ->and($this->liveset->fresh()->started_at->format('H:i'))->toBe('21:30');
});

it('only imports the start time when asked to', function () {
    $this->liveset->tracks()->create(['title' => 'Old - Track', 'timestamp' => 0, 'order' => 1]);

    importCueAsAdmin($this, ['import_cue_tracks' => false, 'import_cue_start' => true])
        ->assertSessionHas('success', 'Liveset file imported, filled in start time.');

    expect($this->liveset->tracks()->pluck('title')->all())->toBe(['Old - Track'])
        ->and($this->liveset->fresh()->started_at->format('H:i'))->toBe('21:30');
});

it('leaves the liveset alone when not asked to fill anything in', function () {
    $this->liveset->update(['started_at' => '2026-11-12 20:00:00', 'duration_in_seconds' => 1800]);
    $this->liveset->tracks()->create(['title' => 'Old - Track', 'timestamp' => 0, 'order' => 1]);

    importCueAsAdmin($this, [])
        ->assertSessionHas('success', 'Liveset file imported.');

    $liveset = $this->liveset->fresh();
    expect($liveset->tracks()->pluck('title')->all())->toBe(['Old - Track'])
        ->and($liveset->started_at->format('H:i'))->toBe('20:00')
        ->and($liveset->duration_in_seconds)->toBe(1800);
});

it('just imports the file when there is no cue or readable duration', function () {
    $this->liveset->update(['duration_in_seconds' => 1800]);
    $this->liveset->tracks()->create(['title' => 'Old - Track', 'timestamp' => 0, 'order' => 1]);

    importCueAsAdmin($this, [
        'path' => 'TFW15/Other/01 REC.mp3',
        'name' => 'stambak.mp3',
        'import_cue_tracks' => true,
        'import_cue_start' => true,
        'import_duration' => true,
    ])->assertSessionHas('success', 'Liveset file imported.');

    Storage::disk('public')->assertExists("livesets/{$this->liveset->id}/stambak.mp3");
    $liveset = $this->liveset->fresh();
    expect($liveset->tracks()->pluck('title')->all())->toBe(['Old - Track'])
        ->and($liveset->started_at)->toBeNull()
        ->and($liveset->duration_in_seconds)->toBe(1800);
});

it('parses an uploaded cue on the liveset page', function () {
    asCueAdmin($this)->postJson(route('util.cue'), ['content' => rekordboxCue()])
        ->assertSuccessful()
        ->assertExactJson([
            'recorded_at' => '2026-11-12T21:30:00+01:00',
            'performer' => 'Stambak',
            'songs' => ['00:00:00 | Bubble Love - Love', '00:01:55 | Antwood - Club Dread'],
        ]);
});
