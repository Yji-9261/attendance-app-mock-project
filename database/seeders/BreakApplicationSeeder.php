<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\User;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Carbon\Carbon;

class BreakApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $application = Application::findOrFail(1);
        $attendance = $application->attendance;
        $break_in = $attendance->breaktimes()->firstOrFail()?->break_in;
        $break_out = $attendance->breaktimes()->firstOrFail()?->break_out;

        $application->breakapplications()->create([
            'break_in' => $break_in?->copy()->addMinutes(30),
            'break_out' => $break_out?->copy()->addMinutes(30),
        ]);
    }
}
