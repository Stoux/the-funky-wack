<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW8 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '8',
            'tag_line' => '?',
            'date' => '2025-05-16',
        ]);
    }

    protected function createLivesets(): void
    {
        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak & Co House',
            'artist_name' => 'Stambak',
            'duration_in_seconds' => 3277,
            'genre' => 'house',
            'bpm' => '125',
            'lineup_order' => 1,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-8/stambak-cos-huis-funky-wack-8-2025-05-16',
            'started_at' => '2025-05-16T18:06:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'BRNCL\'s It\'s a Trap!',
            'artist_name' => 'BRNCL',
            'duration_in_seconds' => 2519,
            'genre' => 'Garage, Trap',
            'bpm' => '130 - 140',
            'lineup_order' => 2,
            'started_at' => '2025-05-16T19:03:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth\'s Moeilijke UK Shit',
            'artist_name' => 'Lionsworth',
            'duration_in_seconds' => 2734,
            'genre' => 'UK Garage, UK Bass',
            'bpm' => '135',
            'lineup_order' => 3,
            'started_at' => '2025-05-16T19:46:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno Hardhuis in de Trance',
            'artist_name' => 'DJarno',
            'duration_in_seconds' => 2663,
            'genre' => 'Hard house, Trance',
            'bpm' => '140 - 160',
            'lineup_order' => 4,
            'started_at' => '2025-05-16T20:33:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s de appel valt niet ver van de Bonzai',
            'artist_name' => 'Stambak',
            'duration_in_seconds' => 1960,
            'genre' => 'Hardcore',
            'bpm' => '150+',
            'lineup_order' => 5,
            'started_at' => '2025-05-16T21:20:00',
        ]);

    }
}
