<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
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
            'user_id' => User::factory(),
            'date' => $date,
            'clock_in' => $date->copy()->startOfDay()->hours(9),
            'clock_out' => $date->copy()->startOfDay()->hours(18),
            'comment' => '',
        ];
    }
}
