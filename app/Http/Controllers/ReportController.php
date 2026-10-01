<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public const REPORT_MONTHS = 6;

    public const STANDARD_WORK_MINUTES = 8 * 60;

    public const START_WORK_HOUR = 9;

    public const END_WORK_HOUR = 18;

    public const OVER_WORK_MINUTES = 10 * 60;

    /**
     * レポート画面表示
     * GET(/attendance/report)
     *
     * @param  Request  $request  リクエスト
     * @return Factory|View
     */
    public function report(Request $request)
    {
        // 月次勤怠レポートを取得
        $sixMonthsAttendaces = $this->getSixMonthAttendances($request);
        $summaries = $sixMonthsAttendaces
            ->map(function ($monthlyAttendances, $yearMonth) {
                $summary = $this->calculateMonthlyAttendanceReport($monthlyAttendances);

                // キーには'Y-m'形式の年月文字列が入る
                // blade表示用に'month'として月のみを格納する
                $summary['month'] = Carbon::parse($yearMonth)->month;

                return $summary;
            })->values();

        // 基本サマリー(総労働時間・総残業時間・平均労働時間 / 日)算出
        $total_work_minutes = $summaries->sum('work_minutes');
        $total_overtime_minutes = $summaries->sum('overtime_minutes');
        $total_days = $summaries->sum('total_day');
        // 0 除算対策
        $avg_work_minutes = $total_days === 0
            ? 0 : $total_work_minutes / $total_days;

        $summary = [
            'total_work_minutes' => $total_work_minutes,
            'total_overtime_minutes' => $total_overtime_minutes,
            'avg_work_minutes' => $avg_work_minutes,
        ];

        // 月次勤怠レポートからbladeに必要な要素のみ取得する
        $monthlyTrend = $summaries->map(function ($summary) {
            return [
                'month' => $summary['month'],
                'work_minutes' => $summary['work_minutes'],
                'overtime_minutes' => $summary['overtime_minutes'],
            ];
        });

        // 今月分のレポート取得
        $anomalies = $summaries->firstWhere('month', Carbon::now()->month);

        return view('reports.index', [
            'summary' => $summary,
            'monthlyTrend' => $monthlyTrend,
            // 今月の異常検知をbladeに必要な要素のみ抜き出す
            'anomalies' => [
                'late_count' => $anomalies['late_count'],
                'early_leave_count' => $anomalies['early_leave_count'],
                'long_work_count' => $anomalies['long_work_count'],
            ],
        ]);
    }

    /**
     * ６ヶ月分の月次勤怠レコード取得
     *
     * @param  Request  $request  リクエスト
     * @return Collection<int|string, Collection<int|string, mixed>>
     */
    private function getSixMonthAttendances(Request $request)
    {
        // 当月を含む6ヶ月分の勤怠レコードを年月毎にグループ化して取得
        $now = Carbon::now();
        $startMonth = $now->copy()->startOfMonth()->subMonthsNoOverflow(self::REPORT_MONTHS - 1);
        $endMonth = $now->copy()->endOfMonth();
        $sixMonthAttendances = $request->user()
            ->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [$startMonth, $endMonth])
            ->get()
            ->groupBy(function ($attendance) {
                return $attendance->date->format('Y-m');
            });

        // 勤怠が存在しない月には空のcollectionを用意する
        while ($startMonth->lt($endMonth)) {
            $yearMonth = $startMonth->format('Y-m');
            if (! $sixMonthAttendances->has($yearMonth)) {
                $sixMonthAttendances->put($yearMonth, collect());
            }
            $startMonth->addMonthNoOverflow();
        }

        // blade表示の関係で、年月(Y-m)形式のキーを降順で並び替え
        return $sixMonthAttendances->sortKeysDesc();
    }

    /**
     * 残業時間を分単位で算出
     *
     * @param  int  $workMinutes  実勤務時間(分単位)
     */
    private function calculateOvertimeMinutes(int $workMinutes): int
    {
        // 1日480分(8時間)を超えた分の時間を残業とする
        $overTime = $workMinutes - self::STANDARD_WORK_MINUTES;
        if ($overTime <= 0) {
            return 0;
        }

        return $overTime;
    }

    /**
     * 遅刻した回数を算出
     *
     * @param  Attendance  $attendance  勤怠レコード
     */
    private function countLate(Attendance $attendance): int
    {
        // 出勤時間が9時超過の場合遅刻とする
        $standardClockIn = $attendance->clock_in
            ->copy()
            ->startOfDay()
            ->hour(self::START_WORK_HOUR);

        return $standardClockIn->lt($attendance->clock_in)
            ? 1 : 0;
    }

    /**
     * 早退した回数を算出
     *
     * @param  Attendance  $attendance  勤怠レコード
     */
    private function countEarlyLeave(Attendance $attendance): int
    {
        // 退勤打刻前は0とする
        if (! $attendance->clock_out) {
            return 0;
        }

        // 退勤時間が18時未満の場合早退とする
        $standardClockOut = $attendance->clock_out
            ->copy()
            ->startOfDay()
            ->hour(self::END_WORK_HOUR);

        return $standardClockOut->gt($attendance->clock_out)
            ? 1 : 0;
    }

    /**
     * 長時間労働回数を算出
     *
     * @param  int  $workMinutes  実勤務時間(分単位)
     */
    private function countLongWork(int $workMinutes): int
    {
        // 実労働時間10時間超で長時間労働とする
        return $workMinutes > self::OVER_WORK_MINUTES
            ? 1 : 0;
    }

    /**
     * 月次レポートを算出
     *
     * @param  mixed  $monthlyAttendances  月毎の勤怠レコード
     * @return array{"early_leave_count": int, "late_count": int, "long_work_count": int, "overtime_minutes": int, "total_day": int, "work_minutes": int}
     */
    private function calculateMonthlyAttendanceReport($monthlyAttendances)
    {
        $report = [
            'work_minutes' => 0,
            'overtime_minutes' => 0,
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
            'total_day' => 0,
        ];

        foreach ($monthlyAttendances as $attendance) {
            $workMinutes = $attendance->calculateWorkMinutes();
            $report['work_minutes'] += $workMinutes;
            $report['overtime_minutes'] += $this->calculateOvertimeMinutes($workMinutes);
            $report['late_count'] += $this->countLate($attendance);
            $report['early_leave_count'] += $this->countEarlyLeave($attendance);
            $report['long_work_count'] += $this->countLongWork($workMinutes);

            // 退勤打刻されたものを１日の経過とする
            if ($attendance->clock_out) {
                $report['total_day'] += 1;
            }
        }

        return $report;
    }
}
