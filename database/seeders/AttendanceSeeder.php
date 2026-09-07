<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        //Attendance::factory()->count(10)->create();

        Attendance::create([
            "user_id" => "2",
            "date" => Carbon::parse("2026-09-01 19:00:00"),
            "clock_in" => Carbon::parse("2026-09-01 10:00:00"),
            "clock_out" => Carbon::parse("2026-09-01 19:00:00"),
        ]);

        Attendance::create([
            "user_id" => "2",
            "date" => Carbon::parse("2026-09-02 21:00:00"),
            "clock_in" => Carbon::parse("2026-09-02 10:00:00"),
            "clock_out" => Carbon::parse("2026-09-02 21:00:00"),
        ]);

    }
}
