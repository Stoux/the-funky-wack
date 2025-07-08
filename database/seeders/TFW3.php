<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW3 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '3',
            'tag_line' => 'De R\'Dam Rave Pre-Party',
            'date' => '2024-04-12',
            'notes' => 'Live recordings of the very low-effort "Funky Wack 3".'
        ]);
    }

    protected function createLivesets(): void
    {
        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s Recovery House',
            'artist_name' => 'Stambak',
            'genre' => 'House',
            'duration_in_seconds' => 1667,
            'lineup_order' => 1,
            'started_at' => '2024-04-12T19:30:04',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack/stambaks-recovery-house-funky-wack-3-2024-04-12',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth Can Swim',
            'artist_name' => 'Lionsworth',
            'genre' => 'House, Electronic',
            'description' => 'Follow up of "Lionsworth Can\'t Swim" @ TFW2.',
            'duration_in_seconds' => 1779,
            'lineup_order' => 2,
            'started_at' => '2024-04-12T19:59:38',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/lionsworth-can-swim-funky-wack-3-2024-04-12',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno is a dancer because Rhythm is a -',
            'artist_name' => 'DJarno',
            'genre' => '90s Classics',
            'duration_in_seconds' => 1563,
            'lineup_order' => 3,
            'started_at' => '2024-04-12T20:30:47',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/djarno-is-a-dancer-because-rhythm-is-a-funky-wack-3-2024-04-12',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'UK Lionsworth, innit?',
            'artist_name' => 'Lionsworth',
            'genre' => 'UK Garage / Bass',
            'duration_in_seconds' => 1763,
            'lineup_order' => 4,
            'started_at' => '2024-04-12T20:58:07',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/uk-lionsworth-innit-funky-wack-3-2024-04-12',
        ]);


        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak Remembers Dubstep',
            'artist_name' => 'Stambak',
            'genre' => 'Dubstep',
            'duration_in_seconds' => 1738,
            'lineup_order' => 5,
            'started_at' => '2024-04-12T21:28:21',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/stambak-remembers-dubstep-funky-wack-3-2024-04-12',
        ]);

        // TODO: BRNCL setje?

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth Neo City',
            'artist_name' => 'Lionsworth',
            'genre' => 'Eurodance',
            'description' => 'Follow up of "Lionworth Neo Trance" @ TFW2.',
            'duration_in_seconds' => 1674,
            'lineup_order' => 7,
            'started_at' => '2024-04-12T22:31:16',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/lionsworth-neo-city-funky-wack-3-2024-04-12',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno\'s Gemiddelde Trommels',
            'artist_name' => 'DJarno',
            'genre' => 'Liquid, DnB',
            'duration_in_seconds' => 1535,
            'lineup_order' => 8,
            'started_at' => '2024-04-12T23:01:49',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/djarnos-gemiddelde-trommels-funky-wack-3-2024-04-12',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak Doem & Verderfenis',
            'artist_name' => 'Stambak',
            'genre' => 'Hard Techno',
            'duration_in_seconds' => 629,
            'description' => 'Forgot to start the recording. Only the last 10 minutes of the recording.',
            'lineup_order' => 9,
            'started_at' => '2024-04-12T23:48:04',
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/stambak-doem-verderfenis-funky-wack-3-2024-04-12',
        ]);
    }
}
