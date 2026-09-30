<?php

namespace Database\Factories;

use App\Models\Checkin;
use App\Models\Pass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checkin>
 */
class CheckinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pass_id' => Pass::factory(),
            'event_id' => fn (array $attributes) => Pass::find($attributes['pass_id'])->event_id,
            'scanned_at' => now(),
            'client_id' => fake()->uuid(),
        ];
    }
}
