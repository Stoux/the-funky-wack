<?php

namespace Database\Seeders;

use App\Models\Edition;
use Illuminate\Database\Seeder;

abstract class BaseEditionSeeder extends Seeder
{
    protected ?Edition $edition = null;

    public function run(): void
    {
        $this->edition = $this->createEdition();
        $this->createLivesets();
    }

    abstract protected function createEdition(): Edition;

    abstract protected function createLivesets(): void;
}
