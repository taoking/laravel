<?php

namespace Database\Seeders;

use Database\Factories\FightFactory;
use Illuminate\Database\Seeder;

class FightSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        FightFactory::times(1000)->create();
    }
}
