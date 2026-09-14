<?php

namespace Database\Factories;

use App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'application_date' => fake()->dateTime()->format('Y-m-d'),
            'new_clock_in' => fake()->dateTime(),
            'new_clock_out' => fake()->dateTime(),
            'comment' => fake()->word(),
        ];
    }
}
