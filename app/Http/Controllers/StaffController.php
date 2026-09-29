<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportCsvRequest;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    /**
     * スタッフ一覧画面表示
     * GET('/admin/staff/list')
     *
     * @return Factory|View
     */
    public function index()
    {
        // スタッフとは全一般ユーザーのこと
        return view('admin.staff-list', [
            'users' => User::where('admin_status', false)->get(),
        ]);
    }

    /**
     * スタッフ勤怠一覧画面表示
     * GET('/admin/attendance/list')
     *
     * @param  Request  $request  リクエスト
     * @return Factory|View
     */
    public function indexAttendance(Request $request)
    {
        // クエリリクエストがないなら現在日付を使用する
        $date = Carbon::now();
        if ($request->has('date')) {
            $date = Carbon::parse($request->query('date'));
        }

        // 対象日の全勤怠レコードを取得する
        $s = $date->copy()->startOfDay()->toDateTime();
        $e = $date->copy()->endOfDay()->toDateTime();
        $attendanceRecords = Attendance::with('breaktimes')
            ->whereBetween('date', [$s, $e])
            ->get();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->format('Y-m-d'),
            'nextDay' => $date->copy()->addDay()->format('Y-m-d'),
            'users' => User::all(),
            'attendanceRecords' => $attendanceRecords,
        ]);
    }

    /**
     * スタッフ勤怠詳細画面表示
     * GET('/admin/attendance/staff/{user}')
     *
     * @param  Request  $request  リクエスト
     * @param  User  $user  ユーザーレコード
     * @return Factory|View
     */
    public function showAttendance(Request $request, User $user)
    {
        // クエリリクエストがないなら現在日付を使用する
        $date = Carbon::now();
        if ($request->has('date')) {
            $date = Carbon::parse($request->query('date'));
        }

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $date,
            'previousMonth' => $date->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonthNoOverflow()->format('Y-m'),
            'formattedAttendanceRecords' => $this->getMonthlyAttendanceRecords($user, $date),
        ]);
    }

    /**
     * CSV出力
     * POST('/export')
     *
     * @param  Request  $request  リクエスト
     * @return StreamedResponse
     */
    public function exportCsv(ExportCsvRequest $request)
    {
        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);

        // 取得年月取得(Y-m-1) 年月が重要なので時間や日は適当で良い
        $date = Carbon::parse($validated['year_month'] . '-1 00:00:00');

        // ファイル名称に年月と対象のユーザーIDを付与する
        // ユーザー名はファイル不可文字がついていた場合失敗するのでIDとする
        $filename =
            $validated['year_month']
            . '月次勤怠データ'
            . 'staff-'
            . $validated['user_id']
            . '.csv';

        // ファイル書き込み処理
        $callback = function () use ($user, $date) {
            $stream = fopen('php://output', 'w');

            // windows向けにUTF8-BOMを付与する
            fwrite($stream, "\xEF\xBB\xBF");

            $header = ['日付', '出勤', '退勤', '休憩', '合計'];
            fputcsv($stream, $header);

            foreach ($this->getMonthlyAttendanceRecords($user, $date) as $attendanceRecords) {
                fputcsv($stream, [
                    $attendanceRecords['date'],
                    $attendanceRecords['clock_in'],
                    $attendanceRecords['clock_out'],
                    $attendanceRecords['total_break_time'],
                    $attendanceRecords['total_time'],
                ]);
            }

            // フッターにユーザー/年月を付与しておく
            $footer =
                $user->name
                . 'さんの'
                . $date->format('Y年n月')
                . '次勤怠データ';

            fputcsv($stream, [$footer]);

            fclose($stream);
        };

        return response()->streamDownload(
            $callback,
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
     * ユーザーごとの月次勤怠データを取得する
     *
     * @return \Illuminate\Database\Eloquent\Collection<mixed, array{"clock_in": mixed, "clock_out": mixed, date: mixed, id: mixed, "total_break_time": mixed, "total_time": mixed>|\Illuminate\Support\Collection<mixed, array{"clock_in": mixed, "clock_out": mixed, date: mixed, id: mixed, "total_break_time": mixed, "total_time": mixed}>}
     */
    private function getMonthlyAttendanceRecords(User $user, Carbon $date)
    {
        $start = $date->copy()->startOfMonth()->toDateTime();
        $end = $date->copy()->endOfMonth()->toDateTime();

        // 対象年月をdate昇順にデータ整形して取得する
        return $user->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get()
            ->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'date' => $attendance->date->isoFormat('MM月DD日(ddd)'),
                    'clock_in' => $attendance->clock_in->format('H:i'),
                    'clock_out' => $attendance->clock_out?->format('H:i'),
                    'total_time' => $attendance->total_time,
                    'total_break_time' => $attendance->total_break_time,
                ];
            });
    }
}
