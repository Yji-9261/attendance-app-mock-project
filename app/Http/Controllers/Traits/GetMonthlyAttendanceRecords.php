<?php

namespace App\Http\Controllers\Traits;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

trait GetMonthlyAttendanceRecords
{
    /**
     * 対象ユーザーの対象月の勤怠レコードをbladeファイル用途に整形して返す
     * @param User $user ユーザーモデル
     * @param Carbon $date 対象月
     * @return Collection<mixed, array{"clock_in": mixed, "clock_out": mixed, date: mixed, id: mixed, "total_break_time": mixed, "total_time": mixed>}
     */
    protected function getMonthlyAttendanceRecords(User $user, Carbon $date): Collection
    {
        // 月内の開始日と終了日取得
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // 月内の勤怠レコードを年月日文字列をKeyとしてコレクションを生成
        $monthlyRecords = $user
            ->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [
                $startOfMonth->toDateTime(),
                $endOfMonth->toDateTime(),
            ])
            ->get()
            ->keyBy(fn($attendance) => $attendance->date->toDateString());

        // 月内の勤怠レコードを整形してコレクションとして返す
        return collect(CarbonPeriod::between($startOfMonth, $endOfMonth))
            ->map(function ($dateTime) use ($monthlyRecords) {
                // 日付に対する勤怠レコードを取得する
                $attendance = $monthlyRecords->get($dateTime->toDateString());

                return [
                    'id' => $attendance?->id,
                    'date' => $dateTime->isoFormat('MM月DD日(ddd)'),
                    'clock_in' => $attendance?->clock_in->format('H:i'),
                    'clock_out' => $attendance?->clock_out?->format('H:i'),
                    'total_time' => $attendance?->total_time,
                    'total_break_time' => $attendance?->total_break_time,
                ];
            });
    }
}
