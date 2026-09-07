<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

use \App\Models\BreakTime;

class BreakTimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        BreakTime::create([
            "attendance_id" => "1",
            "break_in" => Carbon::parse("2026-09-01 12:00:00"),
            "break_out" => Carbon::parse("2026-09-01 13:00:00"),
        ]);

        BreakTime::create([
            "attendance_id" => "1",
            "break_in" => Carbon::parse("2026-09-01 15:00:00"),
            "break_out" => Carbon::parse("2026-09-01 15:30:00"),
        ]);

        BreakTime::create([
            "attendance_id" => "1",
            "break_in" => Carbon::parse("2026-09-01 18:00:00"),
            "break_out" => Carbon::parse("2026-09-01 18:30:00"),
        ]);

    }
}
