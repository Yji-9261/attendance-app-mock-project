<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\BreakTime;

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
