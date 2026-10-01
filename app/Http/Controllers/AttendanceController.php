<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

class AttendanceController extends Controller
{
    /**
     * 勤怠一覧画面
     * GET(/attendance/list)
     *
     * @param  Request  $request  リクエスト
     * @return Factory|View
     */
    public function index(Request $request)
    {
        // リクエストクエリがないなら現在日付を使用する
        $date = Carbon::now();
        if ($request->has('date')) {
            $date = Carbon::parse($request->query('date'));
        }

        // 月内の開始日と終了日取得
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // 月内の勤怠レコードを年月日文字列をKeyとしてコレクションを生成
        $monthlyRecords = $request
            ->user()
            ->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [
                $startOfMonth->toDateTime(),
                $endOfMonth->toDateTime(),
            ])
            ->get()
            ->keyBy(fn($attendance) => $attendance->date->toDateString());

        // blade受け渡し用に月内の勤怠レコードを整形して生成
        $formattedAttendanceRecords =
            collect(CarbonPeriod::between($startOfMonth, $endOfMonth))
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
     *
     * @return Factory|View
     */
    public function create()
    {
        $now = Carbon::now();

        return view('user.attendance-register', [
            'user' => auth()->user(),
            'formattedDate' => $now->isoFormat('YYYY年MM月DD日(ddd)'),

            // formattedTimeはjsで制御しているので空欄でもいいが念の為設定しておく
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 出勤登録画面
     * POST(/attendance)
     *
     * @param  Request  $request  リクエスト
     * @return RedirectResponse|Redirector
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // 現在日付の勤怠情報を取得する
        $now = Carbon::now();

        // ここで取得する$attendanceは日付を跨いでページ更新すると
        // nullが返る可能性があることを考慮する
        $attendance = $user->attendances()
            ->whereDate('date', $now)
            ->first();

        if (
            $request->input('action') === 'clock_in' &&
            $user->attendanceStatus === '勤務外'
        ) {
            $user->attendances()->create([
                'clock_in' => $now,
                'date' => $now,
            ]);
        } elseif (
            $request->input('action') === 'clock_out' &&
            $user->attendanceStatus === '出勤中'
        ) {
            $attendance?->update(['clock_out' => $now]);
        } elseif (
            $request->input('action') === 'break_in' &&
            $user->attendanceStatus === '出勤中'
        ) {
            $attendance?->breaktimes()
                ->create(['break_in' => $now]);
        } elseif (
            $request->input('action') === 'break_out' &&
            $user->attendanceStatus === '休憩中'
        ) {
            $attendance?->breaktimes()
                ->latest('id')
                ->first()?->update(['break_out' => $now]);
        }

        return redirect('/attendance');
    }

    /**
     * 詳細画面
     * GET('/attendance/{attendance}')
     * 一般・管理者共通の勤怠詳細画面
     *
     * @param  Request  $request  リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return Factory|View
     */
    public function show(Request $request, Attendance $attendance)
    {
        $this->authorize('view', $attendance);

        if ($request->user()->admin_status) {
            return $this->showByAdmin($request, $attendance);
        } else {
            return $this->showByUser($request, $attendance);
        }
    }

    /**
     * 出勤登録画面
     * GET(/attendance/{attendance})
     *
     * @param  Request  $request  リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return Factory|View
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
                'year' => $attendance->date->year . '年',
                'date' => $attendance->date->format('n月j日'),
                'application' => $application,
                'breaks' => $breaks,
                'clock_in' => $attendance->clock_in->format('H:i'),
                'clock_out' => $attendance->clock_out?->format('H:i'),
                'comment' => $attendance?->comment,
            ],
        ]);
    }

    /**
     * 勤怠詳細画面
     * GET(/attendance/{attendance})
     *
     * @param  Request  $request  リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return Factory|View
     */
    private function showByAdmin(Request $request, Attendance $attendance)
    {
        // 承認待ち中の勤怠は修正ボタンを表示させないための処理
        $application = $attendance->applications()
            ->where('approval_status', '承認待ち')
            ->first();

        // 各フォーマットはFigma参考
        return view('admin.admin-detail', [
            'user' => $attendance->user,
            'attendanceRecord' => [
                'id' => $attendance->id,
                'year' => $attendance->date->year . '年',
                'date' => $attendance->date->format('n月j日'),
                'comment' => $attendance?->comment,
                'clock_in' => $attendance->clock_in->format('H:i'),
                'clock_out' => $attendance->clock_out?->format('H:i'),
                'application' => $application,

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
}
