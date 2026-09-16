<?php

namespace App\Http\Controllers;

use App\Models\Attendance;

use Illuminate\Http\Request;

use Carbon\Carbon;

class AttendanceController extends Controller
{
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

        /** 
         * add/subMonthの月末問題対応のため月初めに調整したいので
         * endOfMonthとstartOfMonthの呼び出し順番は変えないこと
         */
        $e = $date->endOfMonth()->toDateTime();
        $s = $date->startOfMonth()->toDateTime();

        // 月内の全データを取得し、blade受け渡し用にデータ整形
        $formattedAttendanceRecords = auth()->user()->attendances()
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
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
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
            'formattedTime' => $now->format('H:i'), // この値はjsで制御しているので空欄でもいいが念の為設定しておく
        ]);
    }

    /**
     * 出勤登録画面
     * POST(/attendance)
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $now = Carbon::now();

        // 現在日付の勤怠情報を取得する
        $attendance = $user->attendances()->whereDate('date', $now)->first();

        if (
            $request->input('action') === 'clock_in' &&
            $user->attendanceStatus === '勤務外'
        ) {
            // 出勤ボタン押下時
            $user->attendances()->create([
                'clock_in' => $now,
                'date' => $now,
            ]);
        } else if (
            $request->input('action') === 'clock_out' &&
            $user->attendanceStatus === '出勤中'
        ) {
            // 退勤ボタン押下時
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            if ($attendance) {
                $attendance->update([
                    'clock_out' => $now,
                ]);
            }
        } else if (
            $request->input('action') === 'break_in' &&
            $user->attendanceStatus === '出勤中'
        ) {
            // 休憩入ボタン押下時
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            if ($attendance) {
                $attendance->breaktimes()->create([
                    'break_in' => $now,
                ]);
            }
        } else if (
            $request->input('action') === 'break_out' &&
            $user->attendanceStatus === '休憩中'
        ) {
            // 休憩戻ボタン押下時
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            // 必ずbreaktimes格納してからの動線になるためnullチェックはしていない
            if ($attendance) {
                $attendance->breaktimes()
                    ->latest('id')
                    ->first()
                    ->update([
                        'break_out' => $now
                    ]);
            }
        }
        return redirect('/attendance');
    }

    /**
     * 詳細画面
     * GET('/attendance/{id}')
     * 一般・管理者共通の勤怠詳細画面
     */
    public function show(Request $request, $attendance_id)
    {
        if (auth()->user()->admin_status) {
            return $this->showByAdmin($request, $attendance_id);
        } else {
            return $this->showByUser($request, $attendance_id);
        }
    }

    /**
     * 月毎の６ヶ月分勤怠データ取得
     * @return \Illuminate\Database\Eloquent\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int|string, mixed>>
     */
    private function getSixMonthAttendances()
    {
        // 本日から過去6ヶ月の勤怠レコードを取得する
        $now = Carbon::now();
        // 過去6ヶ月の定義が難しい
        // 1.9/15の過去６ヶ月→4/15〜9/15なのか
        // 2.9/15の過去６ヶ月→4/01〜9/30なのか
        // レポート表記的には2の方が適していると判断する
        //$startMonth = $now->copy()->startOfDay()->subMonthsNoOverflow(5);
        //$endMonth = $now->copy()->endOfDay();
        $startMonth = $now->copy()->startOfMonth()->subMonthsNoOverflow(5);
        $endMonth = $now->copy()->endOfMonth();

        // ６ヶ月分データを月毎にグループ化して取得
        $sixMonthAttendances = auth()->user()
            ->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [$startMonth, $endMonth])
            ->get()
            ->groupBy(function ($attendance) {
                return $attendance->date->format('Y-m');
            });

        // 勤怠が存在しない月には空のcollectionを用意する
        while ($startMonth->timestamp < $endMonth->timestamp) {
            $yearMonth = $startMonth->format('Y-m');
            if (!$sixMonthAttendances->has($yearMonth)) {
                $sixMonthAttendances->put($yearMonth, collect());
            }
            $startMonth->addMonthNoOverflow();
        }

        // 年月(Y-m)形式のキーを降順で並び替え
        return $sixMonthAttendances->sortKeysDesc();
    }


    /**
     * 月毎のレポート算出
     */
    private function calculateMonthlyAttendanceReport($monthlyAttendances)
    {
        $monthlyReport = $monthlyAttendances->reduce(function ($carry, $attendance) {
            $clock_in = $attendance->clock_in;
            $clock_out = $attendance->clock_out;

            // 退勤打刻後なら１日の休憩時間を算出
            $breakTimeMinutes =
                !$clock_out
                ? 0
                : $attendance->breaktimes->sum(function ($breakTime) {
                    if (!$breakTime->break_in || !$breakTime->break_out) {
                        // 休憩時間に空白があるなら0で算出しておく
                        return 0;
                    }
                    return (int) $breakTime->break_in->diffInMinutes($breakTime->break_out);
                });

            // １日の勤務時間を算出、退勤打刻前の場合0として計算
            $diffWorkTimeMinutes =
                $clock_out
                ? (int) $clock_in->diffInMinutes($clock_out)
                : 0;

            // 勤務時間から休憩時間を差し引いて労働時間とする
            $diffWorkTimeMinutes -= $breakTimeMinutes;
            $carry['work_minutes'] += $diffWorkTimeMinutes;

            // 残業時間算出（１日8時間を超えた分の時間を残業とする）
            $overTime = $diffWorkTimeMinutes - 480;
            $carry['overtime_minutes'] += $overTime >= 0 ? $overTime : 0;

            // 遅刻回数カウント(出勤時間が9時超過の場合)
            $lateMinutes = (int) $clock_in
                ->copy()
                ->startOfDay()
                ->hour(9)
                ->diffInMinutes($clock_in, false);

            if ($lateMinutes > 0) {
                $carry['late_count'] += 1;
            }

            // 退勤打刻前ならnullの可能性あり
            if ($clock_out) {
                // 早退回数カウント(退勤時間が18時未満の場合)
                $earlyMinutes = (int) $clock_out
                    ->copy()
                    ->startOfDay()
                    ->hour(18)
                    ->diffInMinutes($clock_out, false);

                if ($earlyMinutes < 0) {
                    $carry['early_leave_count'] += 1;
                }

                // 総合計日数加算、退勤前の勤怠データは平均日数に計上しない
                $carry['total_day'] += 1;
            }

            // 長時間労働日数カウント(10時間を超えた労働時間の場合)
            if ($diffWorkTimeMinutes > 10 * 60) {
                $carry['long_work_count'] += 1;
            }

            return $carry;

        }, [
            'work_minutes' => 0,
            'overtime_minutes' => 0,
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
            'total_day' => 0,
        ]);

        return $monthlyReport;
    }

    /**
     * レポート画面表示
     * GET(/attendance/report)
     */
    public function report(Request $request)
    {
        // 過去６ヶ月分の月毎の勤怠データ
        $sixMonthsAttendaces = $this->getSixMonthAttendances();

        // 月毎のデータを取得
        $summaries = collect();
        foreach ($sixMonthsAttendaces as $key => $monthlyAttendances) {
            $summary = $this->calculateMonthlyAttendanceReport($monthlyAttendances);

            // $keyは'Y-m'形式の年月文字列。月のみをmonthに格納する
            $summary['month'] = Carbon::parse($key)->month;
            $summaries->add($summary);
        }

        // 基本サマリー計算
        $total_work_minutes = $summaries->sum('work_minutes');
        $total_overtime_minutes = $summaries->sum('overtime_minutes');
        $total_days = $summaries->sum('total_day');
        $avg_work_minutes = 0;
        if ($total_days > 0) {
            $avg_work_minutes = $total_work_minutes / $total_days;
        }

        $summary = [
            'total_work_minutes' => $total_work_minutes,
            'total_overtime_minutes' => $total_overtime_minutes,
            'avg_work_minutes' => $avg_work_minutes,
        ];

        // 月毎のトレンド 
        $monthlyTrend = $summaries;

        // 今月分の異常値
        $anomalies = $summaries->firstWhere('month', Carbon::now()->month);

        return view('reports.index', [
            'summary' => $summary,
            'monthlyTrend' => $monthlyTrend,
            'anomalies' => $anomalies,
        ]);
    }

    /**
     * 出勤登録画面
     * GET(/attendance/{id})
     */
    private function showByUser(Request $request, $attendance_id)
    {
        $user = auth()->user();
        $att = $user->attendances()->findOrFail($attendance_id);

        // 承認待ち修正申請データ取得（Nullも許容）
        // 承認待ち中は他の修正申請はない想定のためfirstで問題なし
        $app = $att->applications()
            ->where('approval_status', '承認待ち')
            ->first();

        // 休憩時間をdatetimeからtime変更する 
        $breaks = $att->breaktimes()
            ->get()
            ->map(function ($item) {
                return [
                    'break_in' => $item->break_in->format('H:i'),
                    'break_out' => $item->break_out?->format('H:i'),
                ];
            });

        $data = [
            'id' => $attendance_id,
            'year' => $att->date->year . "年",
            'date' => $att->date->format('n月j日'),
            'application' => $app,
            'breaks' => $breaks,
            'clock_in' => $att->clock_in->format('H:i'),
            'clock_out' => $att->clock_out?->format('H:i'),
            'comment' => $app?->comment,
        ];

        return view('user.user-detail', [
            'data' => $data,
            'user' => $user,
        ]);
    }

    /**
     * 勤怠詳細画面
     * GET(/attendance/{id})
     */
    private function showByAdmin(Request $request, $attendance_id)
    {
        $attendance = Attendance::findOrFail($attendance_id);
        $application = $attendance->applications()
            ->where('approval_status', '承認待ち')
            ->first();

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $attendance->date->year . "年",
            'date' => $attendance->date->format('n月j日'),
            'comment' => $application?->comment,
            'approval_status' => $application?->approval_status,
            'clock_in' => $attendance->clock_in->format('H:i'),
            'clock_out' => $attendance->clock_out?->format('H:i'),
            'breaks' => $attendance->breaktimes->map(function ($bt) {
                // 休憩時間をdatetimeからtime表記として文字列変換する 
                return [
                    'break_in' => $bt->break_in->format('H:i'),
                    'break_out' => $bt->break_out?->format('H:i'),
                ];
            })->toArray(), //blade側でis_arrayしているため配列型に変換必須
        ];

        return view('admin.admin-detail', [
            'attendanceRecord' => $attendanceRecord,
            'user' => $attendance->user,
        ]);
    }
}
