<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BreakTime>
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
        $break_in = Carbon::parse(fake()->datetime()->format('Y-m-d H:i'));
        $break_out = $break_in->copy()->addMinutes(rand(15, 59));

        return [
            'attendance_id' => Attendance::factory(),
            'break_in' => $break_in,
            'break_out' => $break_out,
        ];
    }
}
