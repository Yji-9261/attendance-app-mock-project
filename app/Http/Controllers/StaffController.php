<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\GetMonthlyAttendanceRecords;
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
    use GetMonthlyAttendanceRecords;

    /**
     * スタッフ一覧画面表示
     * GET('/admin/staff/list')
     *
     * @return Factory|View
     */
    public function index(): Factory|View
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
    public function indexAttendance(Request $request): Factory|View
    {
        $date = Carbon::now();
        // 対象日付のクエリリクエストがあるならその日付を対象とする
        if ($request->has('date')) {
            // バリデーションエラー時はメッセージ出さずに元のページにリダイレクトとのみとする
            $validated = $request->validate([
                'date' => 'date_format:Y-m-d',
            ]);
            $date = Carbon::parse($validated['date']);
        }

        // 対象日の勤怠レコードを取得する
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
     * スタッフ勤怠月次勤怠一覧画面表示
     * GET('/admin/attendance/staff/{user}')
     *
     * @param  Request  $request  リクエスト
     * @param  User  $user  ユーザーレコード
     * @return Factory|View
     */
    public function showAttendance(Request $request, User $user): Factory|View
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
     * @param  ExportCsvRequest  $request  CSV出力リクエスト
     * @return StreamedResponse
     */
    public function exportCsv(ExportCsvRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);

        // 取得年月取得(Y-m-1) 年月が重要なので時間や日は適当で良い
        $date = Carbon::parse($validated['year_month'] . '-1 00:00:00');

        // ファイル名称に年月と対象のユーザーIDを付与する
        // ユーザー名はファイル不可文字が付く可能性があるためIDとする
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
}
