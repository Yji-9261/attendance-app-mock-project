<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\BreakApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakApplication>
 */
class BreakApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'break_in' => fake()->datetime(),
            'break_out' => fake()->datetime(),
        ];
    }
}
