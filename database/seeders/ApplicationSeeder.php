<?php

namespace Database\Seeders;

use App\Models\Attendance;

use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $attendance = Attendance::findOrFail(1);
        // $attendance->applications()->create([
        //     'application_date' => $attendance->clock_in->copy()->addDay(),
        //     'new_clock_in' => $attendance->clock_in->copy()->subHour(),
        //     'new_clock_out' => $attendance->clock_out->copy()->addHour(),
        //     'comment' => '出退勤時間誤り',
        // ]);
    }
}
