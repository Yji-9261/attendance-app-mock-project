<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Application;

use \App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\ApplicationRequest;

class AdminController extends Controller
{
    /**
     * 管理者ログイン画面
     * GET(/admin/login)
     */
    public function loginView()
    {
        return view("admin.admin-login");
    }

    /**
     * 管理者ログイン処理
     * POST(/admin/login)
     */
    public function login(AdminLoginRequest $request)
    {
        $validated = $request->validated();

        $user = auth()->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'admin_status' => true, // 管理者権限でのログインのみ許可
        ]);

        if ($user) {
            $request->session()->regenerate();
            return redirect('/admin/attendance/list');
        } else {
            return back()->withErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);
        }
    }

    /**
     * 管理者ログアウト
     * POST(/admin/logout)
     */
    public function logout(Request $request)
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }

    /**
     * 勤怠一覧画面
     * POST(/admin/logout)
     */
    public function index(Request $request)
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
     * 勤怠詳細画面
     * GET(/attendance/{id})
     */
    public static function detail(Request $request, $attendance_id)
    {
        $attendance = Attendance::find($attendance_id);
        $application = $attendance->applications()->where('approval_status', '承認待ち')->first();

        $attendanceRecord = [
            'id' => $attendance_id,
            'year' => $attendance->date->year,
            'date' => $attendance->date->format('m-d'),
            'comment' => $application ? $application->comment : '',
            'approval_status' => $application ? $application->approval_status : '',
            'clock_in' => $attendance->clock_in->format('H:i'),
            'clock_out' => $attendance->clock_out?->format('H:i'),
            'breaks' => $attendance->breaktimes->map(function ($bt) {
                // 休憩時間をdatetimeからtime表記として文字列変換する 
                return [
                    'break_in' => $bt->break_in->format('H:i'),
                    'break_out' => $bt->break_out?->format('H:i'),
                ];
            })->toArray(),
        ];

        return view('admin.admin-detail', [
            'attendanceRecord' => $attendanceRecord,
            'user' => $attendance->user,
        ]);
    }

    /**
     * 勤怠詳細画面
     * POST(/attendance/{id})
     */
    public static function store(ApplicationRequest $request, $attendance_id)
    {
        $att = Attendance::find($attendance_id);
        // 存在チェック
        if (!$att) {
            return abort(404)->withErrors('勤務情報が見つかりません');
        }

        $validated = $request->validated();

        // 入力された時間と出勤日を合成してdatetime生成
        $new_clock_in = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_in']);
        $new_clock_out = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_out']);
        $att->clock_in = $new_clock_in;
        $att->clock_out = $new_clock_out;

        DB::transaction(function () use ($att, $validated) {
            // 休憩時間をすべて削除してから新たに入力された情報で再生成    
            $att->breaktimes()->delete();
            $new_break_ins = $validated['new_break_in'] ?? [];
            $new_break_outs = $validated['new_break_out'] ?? [];

            // 安全のため休憩時間空チェック
            if (!empty($new_break_ins) && !empty($new_break_outs)) {
                foreach ($new_break_ins as $index => $new_break_in) {
                    // 入力された休憩時間が空なら登録しない
                    $new_break_in = $new_break_ins[$index];
                    $new_break_out = $new_break_outs[$index];
                    if ($new_break_in && $new_break_out) {
                        $att->breaktimes()->create([
                            'attendance_id' => $att->id,
                            'break_in' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_in),
                            'break_out' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_out),
                        ]);
                    }
                }
            }
            $att->save();
        });

        return redirect("/attendance/{$attendance_id}");
    }


    public function showApproval(Request $request, $application_id)
    {
        $app = Application::find($application_id);
        $application = new Fluent($app->toArray());
        $application->new_date = $app->application_date;
        $application->proposalBreaks = $app->breakapplications;
        $application->new_clock_in = $app->new_clock_in->format('G:i');
        $application->new_clock_out = $app->new_clock_out->format('G:i');
        $user = $app->attendance->user;

        return view('admin.admin-application-detail', [
            'application' => $application,
            'user' => $user,
        ]);
    }

    public function approve(Request $request, $application_id)
    {
        DB::transaction(function () use ($application_id) {
            $app = Application::findOrFail($application_id);
            $app->approval_status = '承認済み';

            $app->attendance->clock_in = $app->new_clock_in;
            $app->attendance->clock_out = $app->new_clock_out;

            // 休憩時間修正
            $app->attendance->breaktimes()->delete();
            $attendance_id = $app->attendance->id;
            foreach ($app->breakapplications as $app_break) {
                $app->attendance->breaktimes()->create([
                    'attendance_id' => $attendance_id,
                    'break_in' => $app_break->break_in,
                    'break_out' => $app_break->break_out,
                ]);
            }

            $app->attendance->save();
            $app->save();
        });

        return redirect("/stamp_correction_request/approve/{$application_id}");
    }

    public function staffList(Request $request)
    {
        return view("admin.staff-list", [
            "users" => User::where("admin_status", false)->get(),
        ]);
    }

    public function staffAttendance(Request $request, $user_id)
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
            ->get();

        return view("admin.staff-attendance-list", [
            "user" => $user,
            "date" => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords
        ]);
    }
}
