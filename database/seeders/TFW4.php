<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW4 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '4',
            'tag_line' => '9/11 Edition',
            'date' => '2024-09-11',
        ]);
    }

    protected function createLivesets(): void
    {
        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno\'s 80s hit parade',
            'artist_name' => 'DJarno',
            'genre' => '80s',
            'duration_in_seconds' => 1662,
            'lineup_order' => 1,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack3/djarnos-80s-hit-parade-funky-wack-4-2024-09-11',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth\'s Spacey Vibes',
            'artist_name' => 'Lionsworth',
            'genre' => 'House, Electronic',
            'duration_in_seconds' => 1763,
            'lineup_order' => 2,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack3/lionsworths-spacey-vibes-funky-wack-4-2024-09-11',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s WhatTheFuckIsDeze(Slow)Sound',
            'artist_name' => 'Stambak',
            'genre' => 'Bass',
            'duration_in_seconds' => 1703,
            'lineup_order' => 3,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack3/stambacks-whatthefuisdezeslowsound-funky-wack-4-2024-09-11',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'UK Lionsworth, innit?',
            'artist_name' => 'Lionsworth',
            'genre' => 'UK Garage / Bass',
            'duration_in_seconds' => 1763,
            'lineup_order' => 4,
            'soundcloud_url' => 'https://soundcloud.com/the-funky-wack-pt2/uk-lionsworth-innit-funky-wack-3-2024-04-12',
        ]);


        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'BRNCL: Overmono & Friends',
            'artist_name' => 'BRNCL',
            'genre' => 'Electronic',
            'duration_in_seconds' => 1894,
            'lineup_order' => 5,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack3/brncl-overmono-friends-funky-wack-4-2024-09-11',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno presents DJeck-o',
            'artist_name' => 'DJarno',
            'genre' => 'Qult, Electronic, Bass',
            'duration_in_seconds' => 2137,
            'lineup_order' => 6,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack4/djarno-presents-djeck-o-funky-wack-4-2024-09-11',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth Rave',
            'artist_name' => 'Lionsworth',
            'genre' => 'Eurotrance',
            'duration_in_seconds' => 1899,
            'lineup_order' => 7,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack3/lionsworth-rave-funky-wack-4-2024-09-11',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s WhatTheFuckIsDeze(Schnelle)Sound',
            'artist_name' => 'Stambak',
            'genre' => 'Bass, DnB',
            'duration_in_seconds' => 1897,
            'lineup_order' => 7,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack3/stambaks-whatthefuisdezeschnellesound-funky-wack-4-2024-09-11',
        ]);
    }
}
