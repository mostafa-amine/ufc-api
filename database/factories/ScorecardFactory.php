<?php

namespace Database\Factories;

use App\Models\Fight;
use App\Models\Scorecard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scorecard>
 */
class ScorecardFactory extends Factory
{
    protected $model = Scorecard::class;

    public function definition(): array
    {
        return [
            'fight_id' => Fight::factory(),
            'judge_name' => $this->faker->name(),
            'red_score' => $this->faker->randomElement([29, 30, 48, 50]),
            'blue_score' => $this->faker->randomElement([27, 28, 45, 47]),
        ];
    }
}
