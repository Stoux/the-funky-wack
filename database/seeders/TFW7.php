<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW7 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '7',
            'tag_line' => '?',
            'date' => '2025-03-06',
        ]);
    }

    protected function createLivesets(): void
    {

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'BRNCL\'s Funky Fresh Garage',
            'artist_name' => 'BRNCL',
            'duration_in_seconds' => 1899,
            'genre' => 'Garage',
            'lineup_order' => 1,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-6/brncls-funky-fresh-garage-funky-wack-7-2025-03-06',
            'started_at' => '2025-03-06T18:51:00',
        ]);


        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth\'s Funky Classics',
            'artist_name' => 'Lionsworth',
            'duration_in_seconds' => 2946,
            'genre' => 'QULT',
            'lineup_order' => 2,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-7/lionsworths-funky-classics-funky-wack-7-2025-03-06',
            'started_at' => '2025-03-06T19:25:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno\'s Funky Rave',
            'artist_name' => 'DJarno',
            'duration_in_seconds' => 2743,
            'genre' => 'Hard house',
            'lineup_order' => 3,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-7/djarnos-funky-rave-funky-wack-7-2025-03-06',
            'started_at' => '2025-03-06T20:16:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s Funky Wack or Heart Attack',
            'artist_name' => 'Stambak',
            'duration_in_seconds' => 3092,
            'genre' => 'Bass',
            'lineup_order' => 4,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-8/stambaks-funky-wack-or-heart-attack-funky-wack-7-2025-03-06',
            'started_at' => '2025-03-06T21:04:00',
        ]);

    }
}
