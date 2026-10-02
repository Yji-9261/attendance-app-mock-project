<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Testing\TestResponse;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証可能ユーザー
     */
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-13 10:00:00'));

        // ユーザー登録
        $this->admin = User::factory()->create(['admin_status' => true]);

        // 認証
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        // 時刻固定の解除
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * ID12 その日になされた全ユーザーの勤怠情報が正確に確認できる
     * ID12 遷移した際に現在の日付が表示される
     */
    public function testCanViewAllUsersAttendanceForToday(): void
    {
        /**
         * 1. 管理者ユーザーにログインする
         * 2. 勤怠一覧画面を開く
         * result1. その日の全ユーザーの勤怠情報が正確な値になっている
         * result2. 勤怠一覧画面にその日の日付が表示されている
         */

        // 勤怠データの作成
        $this->createAttendancesForDate();

        // 1.管理者ユーザーにログインする
        // 2.勤怠一覧画面を開く
        $response = $this->actingAs($this->admin)
            ->get('/admin/attendance/list')
            ->assertOk()
            ->assertViewIs('admin.admin-attendance-list');

        $this->assertAttendanceListForDate($response, Carbon::now());
    }

    /**
     * ID12 「前日」を押下した時に前の日の勤怠情報が表示される
     */
    public function testCanViewAllUsersAttendanceForYesterday(): void
    {
        /**
         * 1. 管理者ユーザーにログインする
         * 2. 勤怠一覧画面を開く
         * 3.「前日」ボタンを押す
         * result. 前日の日付の勤怠情報が表示される
         */
        // 当日と対象日の勤怠データを作成する
        $date = Carbon::now()->subDay();
        $this->createAttendancesForDate();
        $this->createAttendancesForDate($date);

        // 勤怠一覧を開き、「前日」リンクの遷移先を確認する
        $response = $this->actingAs($this->admin)
            ->get('/admin/attendance/list')
            ->assertOk()
            ->assertViewIs('admin.admin-attendance-list');
        $response->assertSee('href="?date=' . $date->format('Y-m-d') . '"', false);

        // 「前日」リンクのURLにアクセスする
        $response = $this->get('/admin/attendance/list?date=' . $date->format('Y-m-d'))
            ->assertOk()
            ->assertViewIs('admin.admin-attendance-list');
        $this->assertAttendanceListForDate($response, $date);

    }

    /**
     * ID12 「翌日」を押下した時に翌日の勤怠情報が表示される
     */
    public function testCanViewAllUsersAttendanceForTomorrow(): void
    {
        /**
         * 1. 管理者ユーザーにログインする
         * 2. 勤怠一覧画面を開く
         * 3.「翌日」ボタンを押す
         * result. 翌日の日付の勤怠情報が表示される
         */
        // 当日と対象日の勤怠データを作成する
        $date = Carbon::now()->addDay();
        $this->createAttendancesForDate();
        $this->createAttendancesForDate($date);

        // 勤怠一覧を開き、「翌日」リンクの遷移先を確認する
        $response = $this->actingAs($this->admin)
            ->get('/admin/attendance/list')
            ->assertOk()
            ->assertViewIs('admin.admin-attendance-list');
        $response->assertSee('href="?date=' . $date->format('Y-m-d') . '"', false);

        // 「翌日」リンクのURLにアクセスする
        $response = $this->get('/admin/attendance/list?date=' . $date->format('Y-m-d'))
            ->assertOk()
            ->assertViewIs('admin.admin-attendance-list');
        $this->assertAttendanceListForDate($response, $date);
    }



    /**
     * テスト用勤怠データの作成
     */
    private function createAttendancesForDate(?Carbon $targetDate = null): void
    {
        $targetDate = $targetDate ?? Carbon::now();

        // 1件目作成
        $date = $targetDate->copy()->startOfDay()->hour(9);
        Attendance::factory()->create([
            'date' => $date,
            'clock_in' => $date,
            'clock_out' => $date->copy()->hour(18),
        ])->breaktimes()->create([
                    'break_in' => $date->copy()->hour(12),
                    'break_out' => $date->copy()->hour(13),
                ]);

        // 2件目作成
        $date = $targetDate->copy()->startOfDay()->hour(10);
        Attendance::factory()->create([
            'date' => $date,
            'clock_in' => $date,
            'clock_out' => $date->copy()->hour(22),
        ])->breaktimes()->createMany([
                    [
                        'break_in' => $date->copy()->hour(13),
                        'break_out' => $date->copy()->hour(16),
                    ],
                    [
                        'break_in' => $date->copy()->hour(19),
                        'break_out' => $date->copy()->hour(19)->minute(30),
                    ],
                ]);

    }

    /** 指定日の各ユーザーの勤怠情報を確認する。 */
    private function assertAttendanceListForDate(TestResponse $response, Carbon $date): void
    {
        // 日付テスト
        $response->assertSee($date->format('Y/m/d'));

        $users = User::all();
        foreach ($users as $user) {
            // 指定日付の勤怠データを取得
            $attendances = $user->attendances()
                ->whereDate('date', $date)
                ->get();
            foreach ($attendances as $attendence) {
                // ユーザーごとに、名前と勤怠情報が画面の列順に表示されることを確認
                $response->assertSeeInOrder([
                    $user->name,
                    $attendence->clock_in->format('H:i'),
                    $attendence->clock_out->format('H:i'),
                    $attendence->total_break_time,
                    $attendence->total_time,
                    url('/attendance/' . $attendence->id),
                ]);
            }
        }

        // 別の日の勤怠が混ざっていないことを確認する
        $otherAttendances = Attendance::whereDate('date', '!=', $date)->get();
        foreach ($otherAttendances as $attendance) {
            $response->assertDontSee('href="' . url('/attendance/' . $attendance->id) . '"', false);
        }
    }

}
