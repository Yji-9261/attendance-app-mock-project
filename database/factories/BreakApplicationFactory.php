<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\BreakApplication;
use Carbon\Carbon;
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
        $date = Carbon::now();

        return [
            'application_id' => Application::factory(),
            'break_in' => $date->copy()->startOfDay()->hours(13),
            'break_out' => $date->copy()->startOfDay()->hours(14),
        ];
    }
}
