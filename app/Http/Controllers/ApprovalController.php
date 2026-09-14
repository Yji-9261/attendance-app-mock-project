<?php

namespace App\Http\Controllers;

use \App\Models\Application;

use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * 修正承認画面表示
     * GET('/stamp_correction_request/approve/{attendance_correct_request_id}')
     */
    public function show(Request $request, $application_id)
    {
        $app = Application::findOrFail($application_id);

        $application = new Fluent($app->toArray());
        $application->new_date = $app->attendance->date;
        $application->proposalBreaks = $app->breakapplications;
        $application->new_clock_in = $app->new_clock_in->format('G:i');
        $application->new_clock_out = $app->new_clock_out->format('G:i');

        return view('admin.admin-application-detail', [
            'application' => $application,
            'user' => $app->attendance->user,
        ]);
    }

    /**
     * 修正承認処理
     * POST('/stamp_correction_request/approve/{attendance_correct_request_id}')
     */
    public function store(Request $request, $application_id)
    {
        $application = Application::findOrFail($application_id);

        // フロント側で要求出せないようにしているが念の為ガード
        if ($application->approval_status !== '承認待ち') {
            return abort(403, '修正申請承認済みです');
        }

        DB::transaction(function () use ($application) {
            $application->attendance->clock_in = $application->new_clock_in;
            $application->attendance->clock_out = $application->new_clock_out;

            // 休憩時間修正
            $application->attendance->breaktimes()->delete();
            $attendance_id = $application->attendance->id;
            foreach ($application->breakapplications as $app_break) {
                $application->attendance->breaktimes()->create([
                    'attendance_id' => $attendance_id,
                    'break_in' => $app_break->break_in,
                    'break_out' => $app_break->break_out,
                ]);
            }

            $application->attendance->save();

            $application->approval_status = '承認済み';
            $application->save();
        });

        return redirect("/stamp_correction_request/approve/{$application_id}");
    }
}
