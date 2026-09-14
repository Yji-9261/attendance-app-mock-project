<?php

namespace Database\Factories;

use App\Models\Application;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BreakApplication>
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
