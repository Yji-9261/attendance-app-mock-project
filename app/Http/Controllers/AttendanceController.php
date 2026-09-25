<?php

namespace App\Http\Controllers;

use App\Models\Attendance;

use Illuminate\Http\Request;

use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * レポート集計期間は6ヶ月
     * @var int
     */
    public const REPORT_MONTHS = 6;

    /**
     * 通常勤務時間、分単位
     * @var int
     */
    public const STANDARD_WORK_MINUTES = 8 * 60;

    /**
     * 勤務開始時間、この時間より遅れて出勤打刻で遅刻としてカウント
     * @var int
     */
    public const START_WORK_HOUR = 9;

    /**
     * 勤務終了時間、この時間より早くに退勤打刻で遅刻としてカウント
     * @var int
     */
    public const END_WORK_HOUR = 18;

    /**
     * 長時間労働。分単位。この時間より勤務時間がオーバーするとカウント
     * @var int
     */
    public const OVER_WORK_MINUTES = 10 * 60;

    /**
     * 勤怠一覧画面
     * GET(/attendance/list)
     */
    public function index(Request $request)
    {
        // リクエストクエリがないなら現在日付を使用する 
        $date = Carbon::now();
        if ($request->has("date")) {
            $date = Carbon::parse($request->query("date"));
        }

        // 月内の勤怠レコードを取得し、blade受け渡し用にデータ整形
        $s = $date->copy()->startOfMonth()->toDateTime();
        $e = $date->copy()->endOfMonth()->toDateTime();
        $formattedAttendanceRecords = auth()->user()
            ->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [$s, $e])
            ->get()
            ->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'date' => $attendance->date->isoFormat('MM月DD日(ddd)'),
                    'clock_in' => $attendance->clock_in->format('H:i'),
                    'clock_out' => $attendance->clock_out?->format('H:i'),
                    'total_time' => $attendance->total_time,
                    'total_break_time' => $attendance->total_break_time
                ];
            });

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonthNoOverflow()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }

    /**
     * 出勤登録画面
     * GET(/attendance)
     */
    public function create()
    {
        $now = Carbon::now();
        return view("user.attendance-register", [
            'user' => auth()->user(),
            'formattedDate' => $now->isoFormat('YYYY年MM月DD日(ddd)'),

            // formattedTimeはjsで制御しているので空欄でもいいが念の為設定しておく
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 出勤登録画面
     * POST(/attendance)
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        // 現在日付の勤怠情報を取得する
        $now = Carbon::now();
        $attendance = $user->attendances()->whereDate('date', $now)->first();

        if (
            $request->input('action') === 'clock_in' &&
            $user->attendanceStatus === '勤務外'
        ) {
            $user->attendances()->create([
                'clock_in' => $now,
                'date' => $now,
            ]);
        } else if (
            $request->input('action') === 'clock_out' &&
            $user->attendanceStatus === '出勤中'
        ) {
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            $attendance?->update(['clock_out' => $now]);
        } else if (
            $request->input('action') === 'break_in' &&
            $user->attendanceStatus === '出勤中'
        ) {
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            $attendance?->breaktimes()
                ->create(['break_in' => $now]);
        } else if (
            $request->input('action') === 'break_out' &&
            $user->attendanceStatus === '休憩中'
        ) {
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            // 取得したbreaktimesはnullになることはない想定だが念の為チェック
            $attendance?->breaktimes()
                ->latest('id')
                ->first()?->update(['break_out' => $now]);
        }
        return redirect('/attendance');
    }

    /**
     * 詳細画面
     * GET('/attendance/{id}')
     * 一般・管理者共通の勤怠詳細画面
     */
    public function show(Request $request, Attendance $attendance)
    {
        if ($request->user()->admin_status) {
            return $this->showByAdmin($request, $attendance);
        } else {
            return $this->showByUser($request, $attendance);
        }
    }

    /**
     * レポート画面表示
     * GET(/attendance/report)
     */
    public function report(Request $request)
    {
        // 月次勤怠レポートを取得
        $sixMonthsAttendaces = $this->getSixMonthAttendances($request);
        $summaries = $sixMonthsAttendaces
            ->map(function ($monthlyAttendances, $yearMonth) {
                $summary = $this
                    ->calculateMonthlyAttendanceReport($monthlyAttendances);

                // キーには'Y-m'形式の年月文字列が入る
                // blade表示用に'month'として月のみを格納する
                $summary['month'] = Carbon::parse($yearMonth)->month;
                return $summary;
            })->values();

        // 基本サマリー(総労働時間・総残業時間・平均労働時間 / 日)算出
        $total_work_minutes = $summaries->sum('work_minutes');
        $total_overtime_minutes = $summaries->sum('overtime_minutes');
        $total_days = $summaries->sum('total_day');
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
            ]
        ]);
    }

    /**
     * 出勤登録画面
     * GET(/attendance/{id})
     */
    private function showByUser(Request $request, Attendance $attendance)
    {
        // 承認待ち中は他の修正申請はない設計のためfirstで問題なし
        $application = $attendance->applications()
            ->where('approval_status', '承認待ち')
            ->first();

        // 休憩時間を時:分形式でフォーマット(Figma参考)
        $breaks = $attendance->breaktimes()
            ->get()
            ->map(function ($breakTime) {
                return [
                    'break_in' => $breakTime->break_in->format('H:i'),
                    'break_out' => $breakTime->break_out?->format('H:i'),
                ];
            });

        // 各フォーマットはFigma参考
        return view('user.user-detail', [
            'user' => $attendance->user,
            'data' => [
                'id' => $attendance->id,
                'year' => $attendance->date->year . "年",
                'date' => $attendance->date->format('n月j日'),
                'application' => $application,
                'breaks' => $breaks,
                'clock_in' => $attendance->clock_in->format('H:i'),
                'clock_out' => $attendance->clock_out?->format('H:i'),
                'comment' => $application?->comment,
            ]
        ]);
    }

    /**
     * 勤怠詳細画面
     * GET(/attendance/{id})
     */
    private function showByAdmin(Request $request, Attendance $attendance)
    {
        // 承認待ち中は他の修正申請はない設計のためfirstで問題なし
        $application = $attendance->applications()
            ->where('approval_status', '承認待ち')
            ->first();

        // 各フォーマットはFigma参考
        return view('admin.admin-detail', [
            'user' => $attendance->user,
            'attendanceRecord' => [
                'id' => $attendance->id,
                'year' => $attendance->date->year . "年",
                'date' => $attendance->date->format('n月j日'),
                'comment' => $application?->comment,
                'approval_status' => $application?->approval_status,
                'clock_in' => $attendance->clock_in->format('H:i'),
                'clock_out' => $attendance->clock_out?->format('H:i'),

                // blade側でis_arrayしているため配列に変換
                'breaks' => $attendance->breaktimes->map(function ($breakTime) {
                    return [
                        'break_in' => $breakTime->break_in->format('H:i'),
                        'break_out' => $breakTime->break_out?->format('H:i'),
                    ];
                })->toArray(),
            ],
        ]);
    }

    /**
     * ６ヶ月分の月次勤怠レコード取得
     * @return \Illuminate\Database\Eloquent\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int|string, mixed>>
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
            if (!$sixMonthAttendances->has($yearMonth)) {
                $sixMonthAttendances->put($yearMonth, collect());
            }
            $startMonth->addMonthNoOverflow();
        }

        // blade表示の関係で、年月(Y-m)形式のキーを降順で並び替え
        return $sixMonthAttendances->sortKeysDesc();
    }

    /**
     * 休憩を除いた実労働時間を分単位で算出
     * @param mixed $attendance 勤怠レコード
     * @return int
     */
    private function calculateWorkMinutes($attendance)
    {
        // 退勤打刻前の可能性がある。その場合の勤務時間は0とする
        if (!$attendance->clock_out) {
            return 0;
        }

        $workMinutes = (int) $attendance->clock_in->diffInMinutes($attendance->clock_out);
        return $workMinutes - $this->calculateBreakMinutes($attendance);
    }

    /**
     * 1日単位の休憩時間算出
     * @param mixed $attendance　勤怠レコード
     * @return int
     */
    private function calculateBreakMinutes($attendance)
    {
        return
            $attendance->breaktimes->sum(function ($breakTime) {
                // 休憩終了打刻前の可能性がある。その場合の休憩時間は0とする
                if (!$breakTime->break_out) {
                    return 0;
                }
                return (int) $breakTime->break_in
                    ->diffInMinutes($breakTime->break_out);
            });
    }

    /**
     * 残業時間を分単位で算出
     * @param mixed $workMinutes 労働時間(分単位)
     * @return int
     */
    private function calculateOvertimeMinutes($workMinutes)
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
     * @param mixed $attendance 勤怠レコード
     * @return int
     */
    private function countLate($attendance)
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
     * @param mixed $attendance 勤怠レコード
     * @return int
     */
    private function countEarlyLeave($attendance)
    {
        // 退勤打刻前は0とする
        if (!$attendance->clock_out) {
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
     * @param mixed $workMinutes 労働時間(分単位)
     * @return int
     */
    private function countLongWork($workMinutes)
    {
        // 実労働時間10時間超で長時間労働とする
        return $workMinutes > self::OVER_WORK_MINUTES
            ? 1 : 0;
    }

    /**
     * 月次レポートを算出
     * @param mixed $monthlyAttendances 月毎の勤怠レコード
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
            $workMinutes = $this->calculateWorkMinutes($attendance);
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
