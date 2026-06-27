<?php

namespace Database\Factories;

use App\Models\Fighter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fighter>
 */
class FighterFactory extends Factory
{
    protected $model = Fighter::class;

    public function definition(): array
    {
        $id = $this->faker->unique()->regexify('[a-f0-9]{16}');

        return [
            'ufcstats_id' => $id,
            'name' => $this->faker->name('male'),
            'nickname' => $this->faker->optional()->word(),
            'height_in' => $this->faker->numberBetween(62, 80),
            'height_raw' => null,
            'weight_lb' => $this->faker->randomElement([125, 135, 145, 155, 170, 185, 205, 265]),
            'weight_raw' => null,
            'reach_in' => $this->faker->numberBetween(64, 84),
            'reach_raw' => null,
            'stance' => $this->faker->randomElement(['Orthodox', 'Southpaw', 'Switch']),
            'dob' => $this->faker->dateTimeBetween('-45 years', '-22 years')->format('Y-m-d'),
            'dob_raw' => null,
            'wins' => $this->faker->numberBetween(0, 30),
            'losses' => $this->faker->numberBetween(0, 15),
            'draws' => 0,
            'no_contests' => 0,
            'slpm' => $this->faker->randomFloat(2, 1, 8),
            'str_acc' => $this->faker->numberBetween(30, 70),
            'sapm' => $this->faker->randomFloat(2, 1, 8),
            'str_def' => $this->faker->numberBetween(40, 75),
            'td_avg' => $this->faker->randomFloat(2, 0, 6),
            'td_acc' => $this->faker->numberBetween(0, 70),
            'td_def' => $this->faker->numberBetween(0, 90),
            'sub_avg' => $this->faker->randomFloat(2, 0, 3),
            'url' => 'http://ufcstats.com/fighter-details/'.$id,
            'last_scraped_at' => now(),
        ];
    }
}
