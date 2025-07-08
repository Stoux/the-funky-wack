<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW6 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '6',
            'tag_line' => '?',
            'date' => '2025-01-17',
        ]);
    }

    protected function createLivesets(): void
    {

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s Can(t) Outrun',
            'artist_name' => 'Stambak',
            'duration_in_seconds' => 1581,
            'genre' => 'Retrowave',
            'lineup_order' => 1,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-6/stambaks-cant-outrun-funky-wack-6-2025-01-17',
            'started_at' => '2025-01-17T18:34:00',
        ]);


        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno\'s 90s Throwback',
            'artist_name' => 'DJarno',
            'duration_in_seconds' => 2125,
            'genre' => '90s',
            'lineup_order' => 2,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-6/djarnos-90s-throwback-funky-wack-6-2025-01-17',
            'started_at' => '2025-01-17T19:02:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'BRNCL\'s Bass Bonkers',
            'artist_name' => 'BRNCL',
            'duration_in_seconds' => 1628,
            'genre' => 'Garage, Bass',
            'lineup_order' => 3,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack5/brncls-bass-bonkers-funky-wack-6-2025-01-17',
            'started_at' => '2025-01-17T19:40:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth\'s Dancin\' Time',
            'artist_name' => 'Lionsworth',
            'duration_in_seconds' => 2740,
            'genre' => 'Eurodance',
            'lineup_order' => 4,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-6/lionsworths-dancin-time-funky-wack-6-2025-01-17',
            'started_at' => '2025-01-17T20:10:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak Bóbr Gopnik',
            'artist_name' => 'Stambak',
            'duration_in_seconds' => 2075,
            'genre' => 'Russian Hard Bass',
            'lineup_order' => 5,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-6/stambak-bobr-gopnik-funky-wack-6-2025-01-17',
            'started_at' => '2025-01-17T20:56:00',
        ]);

    }
}
