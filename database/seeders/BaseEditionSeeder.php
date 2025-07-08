<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Edition;
use App\Models\Liveset;
use Illuminate\Database\Seeder;

abstract class BaseEditionSeeder extends Seeder
{

    protected ?Edition $edition = null;

    public function run(): void {
        $this->edition = $this->createEdition();
        $this->createLivesets();
    }

    protected abstract function createEdition(): Edition;

    protected abstract function createLivesets(): void;

}
