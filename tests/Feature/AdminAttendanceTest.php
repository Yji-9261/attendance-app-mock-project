<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Database\Factories\BreakTimeFactory;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

use Carbon\Carbon;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証可能ユーザー
     * @var User
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
    public function test_can_view_all_users_attendance_for_today(): void
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
    public function test_can_view_all_users_attendance_for_yesterday(): void
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
    public function test_can_view_all_users_attendance_for_tomorrow(): void
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
     * ID13 勤怠詳細画面に表示されるデータが選択したものになっている
     */
    public function test_can_view_attendance_detail(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * result. 詳細画面の内容が選択した情報と一致する
         */

        // テスト用データ作成
        $this->createAttendancesForDate();
        $attendances = Attendance::all();

        foreach ($attendances as $attendance) {
            $response = $this->actingAs($this->admin)
                ->get("/attendance/{$attendance->id}")
                ->assertOk()
                ->assertViewIs('admin.admin-detail');

            $expected = [
                $attendance->user->name,
                $attendance->date->year . "年",
                $attendance->date->format('n月j日'),
                $attendance->clock_in->format('H:i'),
                $attendance->clock_out->format('H:i'),
            ];

            // 複数回の休憩も、開始・終了時刻が順番に表示されることを確認
            foreach ($attendance->breaktimes as $breaktime) {
                $expected[] = $breaktime->break_in->format('H:i');
                $expected[] = $breaktime->break_out->format('H:i');
            }
            $response->assertSeeInOrder($expected);
        }
    }


    /**
     * ID13 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_shows_validation_error_when_clock_in_is_after_clock_out(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 出勤時間を退勤時間より後に設定する
         * 4. 保存処理をする
         */

        // テスト用データ作成
        $this->createAttendancesForDate();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this->actingAs($this->admin)
            ->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail');

        // 出勤時間を退勤時間より後に設定する
        // 保存処理をする
        $response = $this->from("/attendance/{$attendance->id}")
            ->post("/attendance/{$attendance->id}", [
                "new_clock_in" => '19:00',
                "new_clock_out" => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '備考',
            ]);

        // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
        $response->assertRedirect("/attendance/{$attendance->id}");
        $response->assertSessionHasErrors([
            "new_clock_out" => '出勤時間もしくは退勤時間が不適切な値です'
        ]);

        // 実表示テストも行う
        // リダイレクトなので再度GETで取得
        $this->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail')
            ->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }


    /**
     * ID13 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_shows_validation_error_when_break_in_is_after_clock_out(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 休憩開始時間を退勤時間より後に設定する
         * 4. 保存処理をする
         */

        // テスト用データ作成
        $this->createAttendancesForDate();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this->actingAs($this->admin)
            ->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail');

        // 休憩開始時間を退勤時間より後に設定して保存する
        $response = $this->from("/attendance/{$attendance->id}")
            ->post("/attendance/{$attendance->id}", [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['19:00'],
                'new_break_out' => ['13:00'],
                'comment' => '備考',
            ]);

        // リダイレクト先と、対象項目のエラーメッセージを確認する
        $response->assertRedirect("/attendance/{$attendance->id}");
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);

        // リダイレクト後の詳細画面にエラーメッセージが表示されることを確認
        $this->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail')
            ->assertSee('休憩時間が不適切な値です');

    }

    /**
     * ID13 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_shows_validation_error_when_break_out_is_after_clock_out(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 休憩終了時間を退勤時間より後に設定する
         * 4. 保存処理をする         
         */

        // テスト用データ作成
        $this->createAttendancesForDate();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this->actingAs($this->admin)
            ->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail');

        // 休憩終了時間を退勤時間より後に設定して保存する
        $response = $this->from("/attendance/{$attendance->id}")
            ->post("/attendance/{$attendance->id}", [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['19:00'],
                'comment' => '備考',
            ]);

        // リダイレクト先と、対象項目のエラーメッセージを確認する
        $response->assertRedirect("/attendance/{$attendance->id}");
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な時間です',
        ]);

        // リダイレクト後の詳細画面にエラーメッセージが表示されることを確認
        $this->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail')
            ->assertSee('休憩時間もしくは退勤時間が不適切な時間です');

    }

    /**
     * ID13 備考欄が未入力の場合のエラーメッセージが表示される
     */
    public function test_shows_validation_error_when_comment_is_empty(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 備考欄を未入力のまま保存処理をする         
         */

        // テスト用データ作成
        $this->createAttendancesForDate();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this->actingAs($this->admin)
            ->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail');

        // 備考欄を未入力のまま保存する
        $response = $this->from("/attendance/{$attendance->id}")
            ->post("/attendance/{$attendance->id}", [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '',
            ]);

        // リダイレクト先と、対象項目のエラーメッセージを確認する
        $response->assertRedirect("/attendance/{$attendance->id}");
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);

        // リダイレクト後の詳細画面にエラーメッセージが表示されることを確認
        $this->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail')
            ->assertSee('備考を記入してください');
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

    /**
     * テスト用勤怠データの作成
     * @return void
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
}
