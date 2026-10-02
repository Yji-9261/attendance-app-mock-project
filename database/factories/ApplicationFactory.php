<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
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
        $date = Carbon::now();

        return [
            'attendance_id' => Attendance::factory(),
            'application_date' => $date,
            'new_clock_in' => $date->copy()->startOfDay()->hours(10),
            'new_clock_out' => $date->copy()->startOfDay()->hours(19),
            'comment' => '勤怠修正',
        ];
    }
}
