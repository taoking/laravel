<?php

namespace Database\Seeders;

use Database\Factories\TestFactory;
use Illuminate\Database\Seeder;

class TestTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        TestFactory::times(100)->create();
    }
}
