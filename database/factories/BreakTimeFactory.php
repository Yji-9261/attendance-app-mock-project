<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakTime>
 */
class BreakTimeFactory extends Factory
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
            'attendance_id' => Attendance::factory(),
            'break_in' => $date->copy()->startOfDay()->hours(12),
            'break_out' => $date->copy()->startOfDay()->hours(13),
        ];
    }
}
