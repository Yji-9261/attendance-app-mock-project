<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminCorrectAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者
     */
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

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
     * ID13 勤怠詳細画面に表示されるデータが選択したものになっている
     */
    public function testCanViewAttendanceDetail(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * result. 詳細画面の内容が選択した情報と一致する
         */

        // テスト用データ作成
        $this->createTestUserWithAttendance();
        $attendances = Attendance::all();

        foreach ($attendances as $attendance) {
            $response = $this
                ->get("/attendance/{$attendance->id}")
                ->assertOk()
                ->assertViewIs('admin.admin-detail');

            $expected = [
                $attendance->user->name,
                $attendance->date->year . '年',
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
    public function testShowsValidationErrorWhenClockInIsAfterClockOut(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 出勤時間を退勤時間より後に設定する
         * 4. 保存処理をする
         */

        // テスト用データ作成
        $this->createTestUserWithAttendance();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this
            ->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail');

        // 出勤時間を退勤時間より後に設定する
        // 保存処理をする
        $response = $this->from("/attendance/{$attendance->id}")
            ->post("/attendance/{$attendance->id}", [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '備考',
            ]);

        // 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
        $response->assertRedirect("/attendance/{$attendance->id}");
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
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
    public function testShowsValidationErrorWhenBreakInIsAfterClockOut(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 休憩開始時間を退勤時間より後に設定する
         * 4. 保存処理をする
         */

        // テスト用データ作成
        $this->createTestUserWithAttendance();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this
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
    public function testShowsValidationErrorWhenBreakOutIsAfterClockOut(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 休憩終了時間を退勤時間より後に設定する
         * 4. 保存処理をする
         */

        // テスト用データ作成
        $this->createTestUserWithAttendance();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this
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
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);

        // リダイレクト後の詳細画面にエラーメッセージが表示されることを確認
        $this->get("/attendance/{$attendance->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-detail')
            ->assertSee('休憩時間もしくは退勤時間が不適切な値です');

    }

    /**
     * ID13 備考欄が未入力の場合のエラーメッセージが表示される
     */
    public function testShowsValidationErrorWhenCommentIsEmpty(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 備考欄を未入力のまま保存処理をする
         */

        // テスト用データ作成
        $this->createTestUserWithAttendance();
        $attendance = Attendance::firstOrFail();

        // 管理者ユーザーにログインして勤怠詳細ページを開く
        $response = $this
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

    private function createTestUserWithAttendance()
    {
        $date = Carbon::now()->startOfDay();
        $user = User::factory()->create();
        $user->attendances()->create([
            'date' => $date,
            'clock_in' => $date->copy()->hour(9),
            'clock_out' => $date->copy()->hour(18),
        ])->breaktimes()->create([
                    'break_in' => $date->copy()->hour(12),
                    'break_out' => $date->copy()->hour(13),
                ]);

    }
}
