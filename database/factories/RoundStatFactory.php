<?php

namespace Database\Factories;

use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoundStat>
 */
class RoundStatFactory extends Factory
{
    protected $model = RoundStat::class;

    public function definition(): array
    {
        $head = $this->faker->numberBetween(0, 25);
        $body = $this->faker->numberBetween(0, 10);
        $leg = $this->faker->numberBetween(0, 10);
        $sigLanded = $head + $body + $leg;

        $distance = (int) round($sigLanded * 0.7);
        $clinch = (int) round($sigLanded * 0.2);
        $ground = $sigLanded - $distance - $clinch;

        return [
            'fight_id' => Fight::factory(),
            'fighter_id' => Fighter::factory(),
            'round' => 1,
            'knockdowns' => 0,
            'sig_str_landed' => $sigLanded,
            'sig_str_attempted' => $sigLanded + $this->faker->numberBetween(0, 30),
            'total_str_landed' => $sigLanded + $this->faker->numberBetween(0, 10),
            'total_str_attempted' => $sigLanded + $this->faker->numberBetween(10, 40),
            'takedowns_landed' => $this->faker->numberBetween(0, 2),
            'takedowns_attempted' => $this->faker->numberBetween(0, 4),
            'sub_attempts' => $this->faker->numberBetween(0, 2),
            'reversals' => 0,
            'control_time_sec' => $this->faker->numberBetween(0, 240),
            'head_landed' => $head,
            'head_attempted' => $head + $this->faker->numberBetween(0, 20),
            'body_landed' => $body,
            'body_attempted' => $body + $this->faker->numberBetween(0, 5),
            'leg_landed' => $leg,
            'leg_attempted' => $leg + $this->faker->numberBetween(0, 3),
            'distance_landed' => $distance,
            'distance_attempted' => $distance + $this->faker->numberBetween(0, 20),
            'clinch_landed' => $clinch,
            'clinch_attempted' => $clinch + $this->faker->numberBetween(0, 5),
            'ground_landed' => max(0, $ground),
            'ground_attempted' => max(0, $ground) + $this->faker->numberBetween(0, 5),
        ];
    }

    public function total(): static
    {
        return $this->state(fn () => ['round' => 0]);
    }

    public function round(int $n): static
    {
        return $this->state(fn () => ['round' => $n]);
    }
}
