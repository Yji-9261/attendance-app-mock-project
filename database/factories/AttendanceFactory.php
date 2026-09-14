<?php

namespace Database\Factories;

use App\Models\User;

use Illuminate\Database\Eloquent\Factories\Factory;

use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
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
        $now = Carbon::now();
        $date = fake()->dateTimeBetween($now->startOfMonth(), $now->endOfMonth())->format('Y-m-d');
        $clock_in = Carbon::parse("{$date}" . fake()->numberBetween(7, 8) . ":" . fake()->numberBetween(0, 59));
        $clock_out = $clock_in->copy()
            ->addHours(fake()->numberBetween(7, 10))
            ->addMinutes(fake()->numberBetween(0, 59));

        return [
            'user_id' => User::factory(),
            'date' => $date,
            'clock_in' => $clock_in,
            'clock_out' => $clock_out,
        ];
    }
}
