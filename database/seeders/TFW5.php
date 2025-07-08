<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW5 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '5',
            'tag_line' => '?',
            'date' => '2024-11-07',
        ]);
    }

    protected function createLivesets(): void
    {

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s DanceTrippin 2010',
            'artist_name' => 'Stambak',
            'duration_in_seconds' => 1789, // 29*60 + 49
            'genre' => 'House, Dance',
            'lineup_order' => 1,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack4/stambaks-dancetrippin-2010-funky-wack-5-2024-04-12',
            'started_at' => '2024-11-07T18:31:00+00:00',
        ]);


        // https://soundcloud.com/thefunkywack4/brncls-russian-dark-garage-funky-wack-5-2024-11-07?in=the-funky-wack/sets/funky-wack-5-2024-11-07
        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'BRNCL\'s Russian Dark Garage',
            'artist_name' => 'BRNCL',
            'duration_in_seconds' => 2431, // 40*60 + 31
            'genre' => 'Garage, Electronic',
            'lineup_order' => 2,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack4/brncls-russian-dark-garage-funky-wack-5-2024-11-07',
            'started_at' => '2024-11-07T19:09:00+00:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DonEsley in da DanceHall',
            'artist_name' => 'DonEsley',
            'duration_in_seconds' => 2072, // 34*60 + 32
            'genre' => 'House, Dance',
            'description' => 'DJ EsCSS aka DonEsley aka Zandschepper aka Migraineman aka Scalper aka Spaniard aka lang verhaal kort aka vaste klant toolstation aka cacique man aka dealjager',
            'lineup_order' => 3,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack5/donesley-in-da-dancehall-funky-wack-5-2024-11-07',
            'started_at' => '2024-11-07T19:59:00+00:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'MXRTEN\'s Huisfeest',
            'artist_name' => 'MXRTEN',
            'duration_in_seconds' => 2913, // 48*60 + 33
            'genre' => 'House',
            'lineup_order' => 4,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack4/mxrtens-huisfeest-funky-wack-5-2024-11-07',
            'started_at' => '2024-11-07T20:36:00+00:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth\'s Bass Shit',
            'artist_name' => 'Lionsworth',
            'duration_in_seconds' => 1834, // 30*60 + 34
            'genre' => 'Electronic, Bass',
            'lineup_order' => 5,
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack4/lionsworths-bass-shit-funky-wack-5-2024-11-07',
            'started_at' => '2024-11-07T21:26:00+00:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'DJarno starts the Rave',
            'artist_name' => 'DJarno',
            'duration_in_seconds' => 2881, // 48*60 + 1
            'lineup_order' => 6,
            'genre' => 'Hard House',
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack5/djarno-starts-the-rave-funky-wack-5-2024-11-07',
            'started_at' => '2024-11-07T21:58:00+00:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Lionsworth continues the Rave',
            'artist_name' => 'Lionsworth',
            'duration_in_seconds' => 1778, // 29*60 + 38
            'lineup_order' => 7,
            'genre' => 'Hard House, Eurodance',
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack5/lionsworth-continues-the-rave-funky-wack-5-2024-11-07',
            'started_at' => '2024-11-07T22:47:00+00:00',
        ]);

        \App\Models\Liveset::create([
            'edition_id' => $this->edition->id,
            'title' => 'Stambak\'s Drum\'n\'(Double)Bass',
            'artist_name' => 'Stambak',
            'duration_in_seconds' => 2033, // 33*60 + 53
            'lineup_order' => 8,
            'genre' => 'DnB',
            'soundcloud_url' => 'https://soundcloud.com/thefunkywack5/stambaks-drumndoublebass-funky-wack-5-2024-11-07',
            'started_at' => '2024-11-07T23:18:00+00:00',
        ]);

    }
}
