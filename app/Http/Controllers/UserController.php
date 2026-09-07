<?php

namespace App\Http\Controllers;

use App\Models\BreakApplication;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Http\Requests\ApplicationRequest;

class UserController extends Controller
{
    /**
     * 勤怠一覧画面
     * GET(/attendance/list)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // クエリリクエストがないなら現在日付を使用する 
        $date = Carbon::now();
        if ($request->has("date")) {
            $date = Carbon::parse($request->query("date"));
        }

        // add/subMonthの月末問題対応のため月初めに調整したい
        // endOfMonthとstartOfMonthの呼び出し順番は変えないこと
        $e = $date->endOfMonth()->toDateTime();
        $s = $date->startOfMonth()->toDateTime();
        //$attendances = Attendance::where('user_id', auth()->user()->id)->whereBetween('date', [$s, $e])->get();
        $attendances = $user->attendances()
            ->with('breaktimes')
            ->whereBetween('date', [$s, $e])
            ->get();

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $attendances->collect()
        ]);
    }

    /**
     * 出勤登録画面
     * GET(/attendance)
     */
    public function create()
    {
        return view("user.attendance-register", [
            'user' => auth()->user(),
            'formattedDate' => now(),
            'formattedTime' => now()->timestamp,
        ]);
    }

    /**
     * 出勤登録画面
     * POST(/attendance)
     */
    public function refresh(Request $request)
    {
        $user = auth()->user();
        $now = Carbon::now();

        // 現在日付の勤怠情報を取得する
        $attendance = $user->attendances()->whereDate('date', $now)->first();

        if ($request->input('action') === 'clock_in') {
            //
            // 出勤ボタン押下時
            //
            Attendance::create([
                'user_id' => $user->id,
                'clock_in' => $now,
                'date' => $now,

            ]);
        } else if ($request->input('action') === 'clock_out') {
            //
            // 退勤ボタン押下時
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            //
            if ($attendance) {
                $attendance->update([
                    'clock_out' => $now,
                ]);
            }

        } else if ($request->input('action') === 'break_in') {
            //
            // 休憩入ボタン押下時
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            //
            if ($attendance) {
                BreakTime::create([
                    'attendance_id' => $attendance->id,
                    'break_in' => $now,
                ]);
            }

        } else if ($request->input('action') === 'break_out') {
            //
            // 休憩戻ボタン押下時
            // ページ未更新で日付を跨いだ場合 $attendanceがnullになることあり
            //
            if ($attendance) {
                $attendance
                    ->breaktimes()
                    ->latest()
                    ->first()
                    ->update([
                        'break_out' => $now
                    ]);
            }
        } else {
            // 
        }

        return redirect('/attendance');
        // return view("user.attendance-register", [
        //     'user' => auth()->user(),
        //     'formattedDate' => $now,
        //     'formattedTime' => $now->timestamp,
        // ]);
    }

    /**
     * 出勤登録画面
     * GET(/attendance/{id})
     */
    public static function detail(Request $request, $attendance_id)
    {
        $att = Attendance::where('id', $attendance_id)->first();
        $user = auth()->user();

        // リクエストが認証済みユーザーのものかチェック
        if (!$att) {
            return abort(403);
        }

        // 承認待ち修正申請データ取得（Nullも許容）
        // 承認待ち中は他の修正を受け付けないためfirstで問題なし
        $app = Application::where('attendance_id', $att->id)
            ->where('approval_status', '承認待ち')
            ->first();

        // 休憩時間をdatetimeからtime変更する 
        $breaks = [];
        foreach (BreakTime::where('attendance_id', $att->id)->get() as $item) {
            $breaks[] = [
                'break_in' => $item->break_in->format('H:i'),
                'break_out' => $item->break_out->format('H:i'),
            ];
        }

        $data = [
            'id' => $attendance_id,
            'year' => $att->date->year,
            'date' => $att->date->format('m-d'),
            'application' => $app,
            'breaks' => $breaks,
            'clock_in' => $att->clock_in->format('H:i'),
            'clock_out' => $att->clock_out->format('H:i'),
            'comment' => $app ? $app->comment : '',
        ];

        return view('user.user-detail', [
            'data' => $data,
            'user' => $user,
        ]);
    }

    /**
     * 勤怠詳細画面
     * POST('/attendance/{id}'
     */
    public static function store(ApplicationRequest $request, $attendance_id)
    {
        $user = auth()->user();
        $att = Attendance::where('user_id', $user->id)->first();

        // 認証済みユーザーのものか？
        if (!$att) {
            return abort(403);
        }

        DB::transaction(function () use ($request, $attendance_id, $att) {
            $validated = $request->validated();
            $now = Carbon::now();

            // 入力された時間と出勤日を合成してdatetime生成
            $new_clock_in = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_in']);
            $new_clock_out = Carbon::parse($att->date->toDateString() . ' ' . $validated['new_clock_out']);

            $application = Application::create([
                'attendance_id' => $attendance_id,
                'application_date' => $now,
                'new_clock_in' => $new_clock_in,
                'new_clock_out' => $new_clock_out,
                'comment' => $validated['comment'],
            ]);

            $new_break_ins = $validated['new_break_in'] ?? [];
            $new_break_outs = $validated['new_break_out'] ?? [];
            // 休憩時間登録、安全の為、空かどうかのチェックをしておく
            if (!empty($new_break_ins) && !empty($new_break_ins)) {
                foreach ($new_break_ins as $index => $new_break_in) {
                    // 受け取った休憩時間が空白なら登録しない（休憩時間として削除される）
                    $new_break_in = $new_break_ins[$index];
                    $new_break_out = $new_break_outs[$index];
                    if ($new_break_in && $new_break_out) {
                        // 入力された時間と日付を合成してdatetime生成
                        BreakApplication::create([
                            'application_id' => $application->id,
                            'break_in' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_in),
                            'break_out' => Carbon::parse($att->date->toDateString() . ' ' . $new_break_out),
                        ]);
                    }
                }
            }
        });

        //note: 勤怠修正申請が発生した場合、表示するのは修正前？修正申請中のもの？

        return redirect("/attendance/{$attendance_id}");
    }

    /**
     * 勤怠詳細画面
     * GET('/stamp_correction_request/list'
     */
    public function showApplicationDetail(Request $request, $id)
    {
        $attendance_id = Application::find($id)->attendance->id;
        return redirect("/attendance/{$attendance_id}");
    }
}
