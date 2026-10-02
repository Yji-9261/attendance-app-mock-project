<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

class ApprovalController extends Controller
{
    /**
     * 修正承認画面表示
     * GET('/stamp_correction_request/approve/{application}')
     * 
     * @param Application $application 勤怠修正申請モデル
     * @return Factory|View
     */
    public function show(Application $application): Factory|View
    {
        // Applicationモデルに不足している、bladeファイルに必要なプロパティを付与する
        $applicationData = new Fluent($application->toArray());
        $applicationData->new_date = $application->attendance->date;
        $applicationData->proposalBreaks = $application->breakapplications;
        $applicationData->new_clock_in = $application->new_clock_in->format('G:i');
        $applicationData->new_clock_out = $application->new_clock_out->format('G:i');

        return view('admin.admin-application-detail', [
            'application' => $applicationData,
            'user' => $application->attendance->user,
        ]);
    }

    /**
     * 修正承認処理
     * POST('/stamp_correction_request/approve/{application}')
     * 
     * @param Application $application　勤怠修正申請モデル
     * @return Redirector|RedirectResponse
     */
    public function store(Application $application): RedirectResponse|Redirector
    {
        DB::transaction(function () use ($application) {
            // 割り込みにより多重に処理してしまわないようロック
            $lockApplication = Application::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($lockApplication->approval_status !== '承認待ち') {
                abort(403, '承認済みです');
            }

            // 勤怠修正
            $attendance = $lockApplication->attendance;
            $attendance->clock_in = $lockApplication->new_clock_in;
            $attendance->clock_out = $lockApplication->new_clock_out;
            $attendance->comment = $lockApplication->comment;

            // 休憩時間修正
            $attendance->breaktimes()->delete();
            $attendance_id = $attendance->id;
            foreach ($lockApplication->breakapplications as $application_break) {
                $attendance->breaktimes()->create([
                    'attendance_id' => $attendance_id,
                    'break_in' => $application_break->break_in,
                    'break_out' => $application_break->break_out,
                ]);
            }
            $lockApplication->attendance->save();

            // 勤怠修正申請更新
            $lockApplication->approval_status = '承認済み';
            $lockApplication->save();
        });

        return redirect("/stamp_correction_request/approve/{$application->id}");
    }
}
