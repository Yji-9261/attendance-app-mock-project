<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Attendance;
use App\Http\Requests\ApplicationRequest;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class ApplicationController extends Controller
{
    /**
     * 修正一覧画面
     * GET('/stamp_correction_request/list')
     * 一般・管理者共通のパス指定
     */
    public function index()
    {
        if (auth()->user()->admin_status) {
            return $this->indexByAdmin();
        } else {
            return $this->indexByUser();
        }
    }

    /**
     * 修正詳細画面
     * GET('/stamp_correction_request/list')
     */
    public function show(Request $request, $application_id)
    {
        $attendance_id = Application::findOrFail($application_id)->attendance->id;
        return redirect("/attendance/{$attendance_id}");
    }

    /**
     * 修正処理
     * POST('/attendance/{id}')
     * ※一般・管理者共通の勤怠詳細処理
     */
    public function store(ApplicationRequest $request, $attendance_id)
    {
        if (auth()->user()->admin_status) {
            return $this->storeByAdmin($request, $attendance_id);
        } else {
            return $this->storeByUser($request, $attendance_id);
        }
    }

    /**
     * 管理者による申請一覧画面
     */
    private function indexByAdmin()
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
     */
    private function indexByUser()
    {
        $user = auth()->user();
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
     */
    private function storeByAdmin(ApplicationRequest $request, $attendance_id)
    {
        $att = Attendance::findOrFail($attendance_id);
        $validated = $request->validated();

        // フロント側で要求出せないようにしているが念の為ガード
        if ($att->applications()->where('approval_status', '承認待ち')->exists()) {
            return abort(403, '修正申請承認待ちの勤怠です。承認を行なってから修正を行なってください');
        }

        DB::transaction(function () use ($att, $validated) {

            // 入力された時間と出勤日を合成してdatetime生成
            $new_clock_in = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_in']);
            $new_clock_out = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_out']);
            $att->clock_in = $new_clock_in;
            $att->clock_out = $new_clock_out;
            $att->save();

            // 休憩時間をすべて削除してから新たに入力された情報で再生成    
            $att->breaktimes()->delete();
            $new_break_ins = $validated['new_break_in'] ?? [];
            $new_break_outs = $validated['new_break_out'] ?? [];

            // 安全のため休憩時間空チェック
            foreach ($new_break_ins as $index => $new_break_in) {

                // 安全のため配列外参照チェック
                $new_break_out = $new_break_outs[$index] ?? null;
                if ($new_break_in && $new_break_out) {
                    $att->breaktimes()->create([
                        'attendance_id' => $att->id,
                        'break_in' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_in),
                        'break_out' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_out),
                    ]);
                }
            }
        });

        return redirect("/attendance/{$attendance_id}");
    }

    /**
     * 一般ユーザーによる修正要求処理
     */
    private function storeByUser(ApplicationRequest $request, $attendance_id)
    {
        $att = auth()->user()->attendances()->findOrFail($attendance_id);
        $validated = $request->validated();

        // フロント側で要求出せないようにしているが念の為ガード
        if ($att->applications()->where("approval_status", '承認待ち')->exists()) {
            abort(403, '修正申請承認待ちの勤怠です。管理者に承認を得てから修正を行なってください');
        }

        DB::transaction(function () use ($validated, $attendance_id, $att) {

            $now = Carbon::now();

            // 入力された時間と出勤日を合成してdatetime生成
            $new_clock_in = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_in']);
            $new_clock_out = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_out']);

            $application = $att->applications()->create([
                'attendance_id' => $attendance_id,
                'application_date' => $now,
                'new_clock_in' => $new_clock_in,
                'new_clock_out' => $new_clock_out,
                'comment' => $validated['comment'],
            ]);

            $new_break_ins = $validated['new_break_in'] ?? [];
            $new_break_outs = $validated['new_break_out'] ?? [];

            // 休憩時間登録、安全の為、空かどうかのチェックをしておく
            foreach ($new_break_ins as $index => $new_break_in) {

                // 安全のため配列外参照チェック
                $new_break_out = $new_break_outs[$index] ?? null;
                if ($new_break_in && $new_break_out) {

                    // 入力された時間と日付を合成してdatetime生成
                    $application->breakApplications()->create([
                        'application_id' => $application->id,
                        'break_in' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_in),
                        'break_out' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_out),
                    ]);
                }
            }
        });

        //note: 勤怠修正申請が発生した場合、表示するのは修正前？修正申請中のもの？

        return redirect("/attendance/{$attendance_id}");
    }

}
