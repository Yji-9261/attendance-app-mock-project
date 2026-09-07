<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use \App\Models\Application;
use App\Http\Requests\ApplicationRequest;

class UserOrAdminController extends Controller
{
    /**
     * 勤怠一覧画面
     * GET('/admin/attendance/list')
     */
    public function index()
    {
        if (auth()->user()->admin_status) {
            return view('admin.admin-application-list');
        }

        return view('user.user-application-list');
    }

    /**
     * 申請一覧画面
     * GET('/stamp_correction_request/list')
     * 一般・管理者共通のパス指定
     */
    public function showApplications()
    {
        if (auth()->user()->admin_status) {
            //
            // 管理者
            //
            $applications = Application::with('attendance.user')->get();
            foreach ($applications as $app) {
                $app->user = $app->attendance->user;
                $app->AttendanceRecord = $app->attendance;
            }

            return view('admin.admin-application-list', compact('applications'));
        } else {
            //
            // 一般ユーザー
            //
            $formattedApplications = [];
            foreach (auth()->user()->applications as $app) {
                $formattedApplications[] = [
                    'id' => $app->id,
                    'approval_status' => $app->approval_status,
                    'date' => $app->attendance->date->format('Y/m/d G:i'),
                    'application_date' => $app->application_date->format('Y/m/d G:i'),
                    'comment' => $app->comment,
                ];
            }
            return view('user.user-application-list', [
                'formattedApplications' => $formattedApplications,
                'user' => auth()->user(),
            ]);
        }
    }

    /**
     * 詳細画面
     * GET('/attendance/{id}')
     * 一般・管理者共通の勤怠詳細画面
     */
    public function detail(Request $request, $attendance_id)
    {
        if (auth()->user()->admin_status) {
            return AdminController::detail($request, $attendance_id);
        } else {
            return UserController::detail($request, $attendance_id);
        }
    }
    /**
     * 詳細画面
     * POST('/attendance/{id}')
     * 一般・管理者共通の勤怠詳細処理
     */
    public function store(ApplicationRequest $request, $attendance_id)
    {
        if (auth()->user()->admin_status) {
            return AdminController::store($request, $attendance_id);
        } else {
            return UserController::store($request, $attendance_id);
        }
    }

}
