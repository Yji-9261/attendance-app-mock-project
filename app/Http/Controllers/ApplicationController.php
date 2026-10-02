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
     * @param Request $request リクエスト
     * @return Factory|View
     */
    public function index(Request $request): Factory|View
    {
        if ($request->user()->admin_status) {
            return $this->indexByAdmin($request);
        } else {
            return $this->indexByUser($request);
        }
    }

    /**
     * 勤怠詳細画面
     * GET('/application/{application}')
     *
     * @param  Request  $request  リクエスト
     * @param  Application  $application  勤怠修正申請レコード
     * @return RedirectResponse|Redirector
     */
    public function show(Request $request, Application $application): RedirectResponse|Redirector
    {
        $this->authorize('view', $application);

        $attendanceId = $application->attendance->id;

        return redirect("/attendance/{$attendanceId}");
    }

    /**
     * 修正処理
     * POST('/attendance/{attendance}')
     * ※一般・管理者共通の勤怠詳細処理
     *
     * @param  ApplicationRequest  $request  勤怠修正申請リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return RedirectResponse|Redirector
     */
    public function store(ApplicationRequest $request, Attendance $attendance): RedirectResponse|Redirector
    {
        $this->authorize('update', $attendance);

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
    private function indexByAdmin(Request $request): Factory|View
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
    private function indexByUser(Request $request): Factory|View
    {
        $user = $request->user();
        $applications = $user->applications()
            ->with('attendance')
            ->get();

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $applications
                ->map(function ($application) {
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
     * @param  ApplicationRequest  $request  勤怠修正申請リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return RedirectResponse|Redirector
     */
    private function storeByAdmin(ApplicationRequest $request, Attendance $attendance): RedirectResponse|Redirector
    {
        DB::transaction(function () use ($request, $attendance) {
            [$new_clock_in, $new_clock_out, $comment, $breaks]
                = $this->validatedRequest($request);

            // 割り込み多重処理防止用にロック
            $lockAttendance = $this->lockAttendance($attendance);

            // 勤怠レコード更新
            $lockAttendance->update([
                'clock_in' => $new_clock_in,
                'clock_out' => $new_clock_out,
                'comment' => $comment,
            ]);

            // 休憩レコードを一旦削除し、再生成する
            $lockAttendance->breaktimes()->delete();
            $lockAttendance->breaktimes()->createMany($breaks);
        });

        return redirect("/attendance/{$attendance->id}");
    }

    /**
     * 一般ユーザーによる修正要求処理
     *
     * @param  ApplicationRequest  $request  勤怠修正申請リクエスト
     * @param  Attendance  $attendance  勤怠レコード
     * @return RedirectResponse|Redirector
     */
    private function storeByUser(ApplicationRequest $request, Attendance $attendance): RedirectResponse|Redirector
    {
        DB::transaction(function () use ($request, $attendance) {
            [$new_clock_in, $new_clock_out, $comment, $breaks]
                = $this->validatedRequest($request);

            // 割り込み多重処理防止用にロック
            $lockAttendance = $this->lockAttendance($attendance);

            // 勤怠修正申請登録
            $application = $lockAttendance->applications()
                ->create([
                    'application_date' => Carbon::now(),
                    'new_clock_in' => $new_clock_in,
                    'new_clock_out' => $new_clock_out,
                    'comment' => $comment,
                ]);

            // 休憩修正申請生成
            $application->breakApplications()->createMany($breaks);
        });

        return redirect("/attendance/{$attendance->id}");
    }

    /**
     * 検証済みの入力を、勤怠・休憩の保存用データに整形する
     * 
     * @param ApplicationRequest $request 勤怠修正申請リクエスト
     * @return array{Carbon,Carbon,string,array}
     */
    private function validatedRequest(ApplicationRequest $request): array
    {
        $validated = $request->validated();
        $breaks = [];

        // 休憩修正申請時間を更新
        $new_break_ins = $validated['new_break_in'] ?? [];
        $new_break_outs = $validated['new_break_out'] ?? [];

        foreach ($new_break_ins as $index => $new_break_in) {
            // 入力フォーム上、休憩開始・終了は1対1にしているが
            // 念の為チェックする
            $new_break_out = $new_break_outs[$index] ?? null;
            if ($new_break_out) {
                $breaks[] = [
                    'break_in' => Carbon::parse($new_break_in),
                    'break_out' => Carbon::parse($new_break_out),
                ];
            }
        }

        return [
            Carbon::parse($validated['new_clock_in']),
            Carbon::parse($validated['new_clock_out']),
            $validated['comment'],
            $breaks,
        ];
    }

    /**
     * 勤怠レコード更新時ロック
     *
     * @param  Attendance  $attendance  勤怠モデル
     * @return Attendance
     */
    private function lockAttendance(Attendance $attendance): Attendance
    {
        // 割り込みにより多重に処理してしまわないようロック
        $lockAttendance = Attendance::query()
            ->lockForUpdate()
            ->findOrFail($attendance->id);

        if ($lockAttendance->applications()->where('approval_status', '承認待ち')->exists()) {
            abort(403, '承認待ちのため修正できません');
        }

        return $lockAttendance;
    }
}
