<?php

namespace Database\Seeders;

use App\Models\Attendance;

use Illuminate\Database\Seeder;

class BreakTimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $attendance = Attendance::findOrFail(1);
        // $attendance->breaktimes()->create([
        //     'break_in' => $attendance->clock_in->copy()->hour(12),
        //     'break_out' => $attendance->clock_in->copy()->hour(13),
        // ]);
    }
}
