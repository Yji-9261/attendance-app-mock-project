<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 前月/今月/翌月のdatetime生成
        $currentMonth = Carbon::now()
            ->startOfDay()
            ->day(15)
            ->hour(9);
        $previousMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        // 一般ユーザーをひとつ取得
        $user = User::where('admin_status', false)->firstOrFail();

        // 前月の勤怠情報を生成
        $user->attendances()->create([
            'date' => $previousMonth->copy()->hour(9),
            'clock_in' => $previousMonth->copy()->hour(9),
            'clock_out' => $previousMonth->copy()->hour(18),
        ]);

        // 今月の勤怠情報を生成
        $user->attendances()->create([
            'date' => $currentMonth->copy()->hour(9),
            'clock_in' => $currentMonth->copy()->hour(9),
            'clock_out' => $currentMonth->copy()->hour(18),
        ]);

        // 翌月の勤怠情報を生成
        $user->attendances()->create([
            'date' => $nextMonth->copy()->hour(9),
            'clock_in' => $nextMonth->copy()->hour(9),
            'clock_out' => $nextMonth->copy()->hour(18),
        ]);
    }
}
