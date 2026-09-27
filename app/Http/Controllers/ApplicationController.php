<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    /**
     * 修正一覧画面
     * GET('/stamp_correction_request/list')
     * 一般・管理者共通のパス指定
     *
     * @return Factory|View
     */
    public function index(Request $request)
    {
        if (auth()->user()->admin_status) {
            return $this->indexByAdmin($request);
        } else {
            return $this->indexByUser($request);
        }
    }

    /**
     * 修正詳細画面
     * GET('/application/{application}')
     *
     * @param  Request  $request  リクエスト
     * @param  Application  $application  勤怠修正申請レコード
     * @return RedirectResponse|Redirector
     */
    public function show(Request $request, Application $application)
    {
        $attendanceId = $application->attendance->id;

        return redirect("/attendance/{$attendanceId}");
    }

    /**
     * 修正処理
     * POST('/attendance/{attendance}')
     * ※一般・管理者共通の勤怠詳細処理
     *
     * @param  ApplicationRequest  $request  リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return RedirectResponse|Redirector
     */
    public function store(ApplicationRequest $request, Attendance $attendance)
    {
        if ($request->user()->admin_status) {
            return $this->storeByAdmin($request, $attendance);
        } else {
            return $this->storeByUser($request, $attendance);
        }
    }

    /**
     * 管理者による申請一覧画面
     *
     * @param  Request  $request  リクエスト
     * @return Factory|View
     */
    private function indexByAdmin(Request $request)
    {
        $applications = Application::with('attendance.user')->get();

        // bladeファイルに合わせてプロパティ追加
        foreach ($applications as $application) {
            $application->user = $application->attendance->user;
            $application->AttendanceRecord = $application->attendance;
        }

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 一般ユーザーによる申請一覧画面
     *
     * @param  Request  $request  リクエスト
     * @return Factory|View
     */
    private function indexByUser(Request $request)
    {
        $user = $request->user();
        $applications = $user->applications()
            ->with('attendance')
            ->get();

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $applications->map(function ($application) {
                return [
                    'id' => $application->id,
                    'approval_status' => $application->approval_status,
                    'date' => $application->attendance->date->format('Y/m/d'),
                    'application_date' => $application->application_date->format('Y/m/d'),
                    'comment' => $application->comment,
                ];
            }),
        ]);
    }

    /**
     * 管理者による修正処理
     *
     * @param  ApplicationRequest  $request  リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return RedirectResponse|Redirector
     */
    private function storeByAdmin(ApplicationRequest $request, Attendance $attendance)
    {
        $validated = $request->validated();

        // 管理者による対象勤怠の修正画面オープン中にユーザーが対象勤怠の修正を行った場合に発生する
        // 承認待ちの勤怠修正ガード
        if ($attendance->applications()->where('approval_status', '承認待ち')->exists()) {
            abort(403, '修正申請承認待ちの勤怠です。管理者に承認を得てから修正を行なってください');
        }

        DB::transaction(function () use ($attendance, $validated) {
            // 入力された時間と出勤日を合成してdatetime生成
            $attendance->clock_in = Carbon::parse($validated['new_clock_in']);
            $attendance->clock_out = Carbon::parse($validated['new_clock_out']);
            $attendance->save();

            // 休憩時間をすべて削除してからリクエストの内容で再生成
            $attendance->breaktimes()->delete();
            $new_break_ins = $validated['new_break_in'] ?? [];
            $new_break_outs = $validated['new_break_out'] ?? [];

            // 休憩時間を更新
            foreach ($new_break_ins as $index => $new_break_in) {
                // 入力上はnew_break_insもnew_break_outsも同一個数だが年のためチェック
                $new_break_out = $new_break_outs[$index] ?? null;
                if ($new_break_out) {
                    $attendance->breaktimes()->create([
                        'break_in' => Carbon::parse($new_break_in),
                        'break_out' => Carbon::parse($new_break_out),
                    ]);
                }
            }
        });

        return redirect("/attendance/{$attendance->id}");
    }

    /**
     * 一般ユーザーによる修正要求処理
     *
     * @param  ApplicationRequest  $request  リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return RedirectResponse|Redirector
     */
    private function storeByUser(ApplicationRequest $request, Attendance $attendance)
    {
        $validated = $request->validated();

        // 管理者による勤怠修正画面オープン中にユーザーが対象勤怠の修正申請を行った場合に発生する
        // 承認待ちの勤怠修正をガード
        if ($attendance->applications()->where('approval_status', '承認待ち')->exists()) {
            abort(403, '修正申請承認待ちの勤怠です。管理者に承認を得てから修正を行なってください');
        }

        DB::transaction(function () use ($validated, $attendance) {
            // 入力された時間と出勤日を合成してdatetime生成
            $new_clock_in = Carbon::parse($validated['new_clock_in']);
            $new_clock_out = Carbon::parse($validated['new_clock_out']);

            $application = $attendance->applications()->create([
                'application_date' => Carbon::now(),
                'new_clock_in' => $new_clock_in,
                'new_clock_out' => $new_clock_out,
                'comment' => $validated['comment'],
            ]);

            $new_break_ins = $validated['new_break_in'] ?? [];
            $new_break_outs = $validated['new_break_out'] ?? [];

            // 休憩修正申請時間を更新
            foreach ($new_break_ins as $index => $new_break_in) {
                // 入力上はnew_break_insもnew_break_outsも同一個数だが年のためチェック
                $new_break_out = $new_break_outs[$index] ?? null;
                if ($new_break_out) {
                    $application->breakApplications()->create([
                        'application_id' => $application->id,
                        'break_in' => Carbon::parse($new_break_in),
                        'break_out' => Carbon::parse($new_break_out),
                    ]);
                }
            }
        });

        // note: 勤怠修正申請が発生した場合、表示するのは修正前？修正申請中のもの？

        return redirect("/attendance/{$attendance->id}");
    }
}
