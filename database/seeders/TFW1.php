<?php

namespace Database\Seeders;

use App\Models\Edition;

class TFW1 extends BaseEditionSeeder
{
    protected function createEdition(): Edition
    {
        return \App\Models\Edition::create([
            'number' => '1',
            'tag_line' => 'Wacky Beats',
            'date' => '2024-01-19',
        ]);
    }

    protected function createLivesets(): void
    {
        // TODO: There is no list for this?
    }
}
