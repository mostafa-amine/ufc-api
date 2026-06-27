<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fight>
 */
class FightFactory extends Factory
{
    protected $model = Fight::class;

    public function definition(): array
    {
        $id = $this->faker->unique()->regexify('[a-f0-9]{16}');

        return [
            'ufcstats_id' => $id,
            'event_id' => Event::factory(),
            'bout_order' => $this->faker->numberBetween(1, 13),
            'weight_class' => $this->faker->randomElement([
                'Lightweight', 'Welterweight', 'Featherweight', 'Bantamweight', 'Middleweight',
            ]),
            'is_title_bout' => false,
            'scheduled_rounds' => 3,
            'time_format_raw' => '3 Rnd (5-5-5)',
            'red_fighter_id' => Fighter::factory(),
            'blue_fighter_id' => Fighter::factory(),
            'winner_fighter_id' => null,
            'outcome' => 'win',
            'method' => 'Decision - Unanimous',
            'method_detail' => null,
            'end_round' => 3,
            'end_time_sec' => 300,
            'referee' => $this->faker->name(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Fight $fight) {
            // Default a "win" outcome to the red corner unless a winner was set explicitly.
            if ($fight->outcome === 'win' && ! $fight->winner_fighter_id) {
                $fight->forceFill(['winner_fighter_id' => $fight->red_fighter_id])->save();
            }
        });
    }

    public function titleBout(): static
    {
        return $this->state(fn () => [
            'is_title_bout' => true,
            'scheduled_rounds' => 5,
            'time_format_raw' => '5 Rnd (5-5-5-5-5)',
        ]);
    }
}
