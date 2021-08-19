<?php

namespace Database\Factories;

use App\Models\Model;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Test::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
//        $table->id();
//        $table->timestamps();
//        $table->string('name',128);
//        $table->ipAddress('ip');
//        $table->enum('is_use',['1','2','3']);
        return [
            'name'  => $this->faker->name,
            'ip'    => $this->faker->ipv4(),
            'is_use'=> mt_rand(1,3),
        ];
    }
}
