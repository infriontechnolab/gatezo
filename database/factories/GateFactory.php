<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Gate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gate>
 */
class GateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Gate '.fake()->unique()->numberBetween(1, 999),
            'code' => 'G'.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
