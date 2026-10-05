<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => '9'.fake()->unique()->numerify('#########'),
            'name' => fake()->firstName().' '.fake()->lastName(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    public function optedIn(): static
    {
        return $this->state(['marketing_opt_in' => true, 'marketing_opt_in_at' => now()]);
    }
}
