<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $id = $this->faker->unique()->regexify('[a-f0-9]{16}');

        return [
            'ufcstats_id' => $id,
            'name' => 'UFC '.$this->faker->unique()->numberBetween(1, 320),
            'date' => $this->faker->dateTimeBetween('-15 years', 'now')->format('Y-m-d'),
            'location_raw' => $this->faker->city().', United States',
            'city' => $this->faker->city(),
            'state' => null,
            'country' => 'United States',
            'status' => 'completed',
            'url' => 'http://ufcstats.com/event-details/'.$id,
            'last_scraped_at' => now(),
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'status' => 'upcoming',
            'date' => $this->faker->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
        ]);
    }
}
