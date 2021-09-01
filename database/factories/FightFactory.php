<?php

namespace Database\Factories;

use App\Models\Fight;
use Illuminate\Database\Eloquent\Factories\Factory;

class FightFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Fight::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            //
            'name'     => $this->faker->name,
            'content'  => $this->faker->url,
            'status'   => $this->faker->randomKey([1,2,3,4]),
            'num'      =>  mt_rand(1,65545),
            'sort_num' =>  mt_rand(1,256),
        ];
    }
}
