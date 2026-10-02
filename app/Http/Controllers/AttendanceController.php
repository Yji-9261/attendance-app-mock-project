<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\GetMonthlyAttendanceRecords;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

class AttendanceController extends Controller
{
    use GetMonthlyAttendanceRecords;

    /**
     * 勤怠一覧画面
     * GET(/attendance/list)
     *
     * @param  Request  $request  リクエスト
     * @return Factory|View
     */
    public function index(Request $request): Factory|View
    {
        $date = Carbon::now();
        // 対象日付のクエリリクエストがあるならその日付を対象とする
        if ($request->has('date')) {
            // バリデーションエラー時はメッセージ出さずに元のページにリダイレクトとのみとする
            $validated = $request->validate([
                'date' => 'date_format:Y-m',
            ]);
            $date = Carbon::parse($validated['date']);
        }

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonthNoOverflow()->format('Y-m'),
            'formattedAttendanceRecords' => $this->getMonthlyAttendanceRecords($request->user(), $date),
        ]);
    }

    /**
     * 勤怠打刻画面
     * GET(/attendance)
     * 
     * @param Request $request リクエスト
     * @return Factory|View
     */
    public function create(Request $request): Factory|View
    {
        $now = Carbon::now();

        return view('user.attendance-register', [
            'user' => $request->user(),
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
    public function store(Request $request): RedirectResponse|Redirector
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
    public function show(Request $request, Attendance $attendance): Factory|View
    {
        $this->authorize('view', $attendance);

        // 承認待ち中の勤怠修正申請は一つだけの設計のためfirstで問題なし
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

        // 勤怠詳細データを生成
        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $attendance->date->year . '年',
            'date' => $attendance->date->format('n月j日'),
            'application' => $application,
            'breaks' => $breaks,
            'clock_in' => $attendance->clock_in->format('H:i'),
            'clock_out' => $attendance->clock_out?->format('H:i'),
            'comment' => $attendance->comment,
        ];

        // 一般ユーザー、管理者用に項目の調整
        if ($request->user()->admin_status) {
            // 休憩時間はblade側で配列として扱うため変換
            $attendanceRecord['breaks'] = $breaks->toArray();

            return view('admin.admin-detail', [
                'user' => $attendance->user,
                'attendanceRecord' => $attendanceRecord,
            ]);
        } else {
            return view('user.user-detail', [
                'user' => $attendance->user,
                'data' => $attendanceRecord,
            ]);
        }
    }
}
