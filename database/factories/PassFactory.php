<?php

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\Pass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pass>
 */
class PassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendee_id' => Attendee::factory(),
            'event_id' => fn (array $attributes) => Attendee::find($attributes['attendee_id'])->event_id,
        ];
    }
}
