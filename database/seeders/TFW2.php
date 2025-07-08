<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW2 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '2',
            'tag_line' => 'The Funky Cat Pre-Party',
            'date' => '2024-03-15',
            'notes' => 'Live recordings of the very low-effort "Funky Wack 2", pre-party for The Funky Cat 18.'
        ]);
    }

    protected function createLivesets(): void
    {
        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak in een Luchtballon',
            'artist_name' => 'Stambak',
            'genre' => 'House',
            'duration_in_seconds' => 726,
            'lineup_order' => 1,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack/stambak-in-een-luchtballon-funky-wack-2-2024-03-14',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth Can\'t Swim',
            'artist_name' => 'Lionsworth',
            'genre' => 'House',
            'duration_in_seconds' => 1763,
            'lineup_order' => 2,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack/lionsworth-cant-swim-funky',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak Bass Wobbles',
            'artist_name' => 'Stambak',
            'genre' => 'Bass House',
            'duration_in_seconds' => 1866,
            'lineup_order' => 3,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack/stambak-bass-wobbles-funky',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth Neo Trance',
            'artist_name' => 'Lionsworth',
            'genre' => 'Neo Trance',
            'duration_in_seconds' => 1762,
            'lineup_order' => 4,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack/lionsworth-neo-trance-funky',
        ]);


        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak: Bassbak',
            'artist_name' => 'Stambak',
            'bpm' => '130 - 155',
            'genre' => 'Bass / Dubstep / Hard Techno',
            'duration_in_seconds' => 1787,
            'lineup_order' => 5,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack/stambak-bass-wobbles-funky',
        ]);
    }
}
