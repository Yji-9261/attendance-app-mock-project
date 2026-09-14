<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;

use \App\Http\Requests\AdminLoginRequest;

use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;


class StaffController extends Controller
{
    /**
     * スタッフ一覧画面表示
     * GET('/admin/staff/list')
     */
    public function index()
    {
        // 一般ユーザーのみ取得
        return view("admin.staff-list", [
            "users" => User::where("admin_status", false)->get(),
        ]);
    }

    /**
     * スタッフ勤怠一覧画面表示
     * GET('/admin/attendance/list')
     */
    public function indexAttendance(Request $request)
    {
        // クエリリクエストがないなら現在日付を使用する 
        $date = Carbon::now();
        if ($request->has("date")) {
            $date = Carbon::parse($request->query("date"));
        }

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
     * GET('/admin/attendance/staff/{id}')
     */
    public function showAttendance(Request $request, $user_id)
    {
        $user = User::findOrFail($user_id);

        // クエリリクエストがないなら現在日付を使用する 
        $date = Carbon::now();
        if ($request->has("date")) {
            $date = Carbon::parse($request->query("date"));
        }

        // add/subMonthの月末問題対応のため月初めに調整したい
        // endOfMonthとstartOfMonthの呼び出し順番は変えないこと
        $e = $date->endOfMonth()->toDateTime();
        $s = $date->startOfMonth()->toDateTime();
        $formattedAttendanceRecords = $user->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [$s, $e])
            ->get()
            ->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'date' => $attendance->date->isoFormat('MM月DD日(ddd)'),
                    'clock_in' => $attendance->clock_in->format('H:i'),
                    'clock_out' => $attendance->clock_out?->format('H:i'),
                    'total_time' => $attendance->total_time,
                    'total_break_time' => $attendance->total_break_time
                ];
            });




        return view("admin.staff-attendance-list", [
            "user" => $user,
            "date" => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords
        ]);
    }
}
