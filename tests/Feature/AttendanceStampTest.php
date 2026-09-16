<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Carbon\Carbon;

class AttendanceStampTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証可能ユーザー
     * @var User
     */
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // ユーザー登録
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        // 時刻固定の解除
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * ID4 日時取得機能テスト 現在の日時情報がUIと同じ形式で出力されている
     * @return void
     */
    public function test_attendance_datetime_get(): void
    {
        /**
         * 1.打刻ページ表示テスト
         * 2.時刻表示テスト
         */

        $now = Carbon::now();

        // 1.打刻ページ表示テスト
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertViewIs('user.attendance-register');

        // 2.日時表示テスト
        $response->assertSee($now->isoFormat('YYYY年MM月DD日(ddd)'));
        $response->assertSee($now->format('H:i'));
    }

    /**
     * ID5 ステータス確認機能 
     * 勤務外ステータスが正しく表示される
     * @return void
     */
    public function test_attendance_status_not_working(): void
    {
        /**
         * 1.打刻ページ表示テスト
         * 2.「勤務外」表示テスト
         */

        // 1.打刻ページ表示テスト
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertViewIs('user.attendance-register');

        // 2.「勤務外」表示テスト
        $response->assertSee('勤務外');
    }

    /**
     * ID5 ステータス確認機能 
     * 出勤中ステータスが正しく表示される
     * @return void
     */
    public function test_attendance_status_working(): void
    {
        /**
         * 1.出勤中ユーザー作成
         * 2.打刻ページ表示テスト
         * 3.「出勤中」表示テスト
         */

        $now = Carbon::now();

        // 1.出勤中ユーザー作成
        $this->user->attendances()->create([
            'date' => $now,
            'clock_in' => $now,
        ]);

        // 2.打刻ページ表示テスト
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertViewIs('user.attendance-register');

        // 3.「出勤中」表示テスト
        $response->assertSee('出勤中');
    }

    /**
     * ID5 ステータス確認機能 
     * 休憩中ステータスが正しく表示される
     * @return void
     */
    public function test_attendance_status_breaking(): void
    {
        /**
         * 1.休憩中ユーザー作成
         * 2.打刻ページ表示テスト
         * 3.「休憩中」表示テスト
         */

        $now = Carbon::now();

        // 1.休憩中ユーザー作成
        $attendance = $this->user->attendances()->create([
            'date' => $now,
            'clock_in' => $now,
        ]);
        $attendance->breaktimes()->create([
            'break_in' => $now,
        ]);

        // 2.打刻ページ表示テスト
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertViewIs('user.attendance-register');

        // 3.「休憩中」表示テスト
        $response->assertSee('休憩中');
    }


    /**
     * ID5 ステータス確認機能 
     * 退勤済みステータスが正しく表示される
     * @return void
     */
    public function test_attendance_status_finshed(): void
    {
        /**
         * 1.退勤済みユーザー作成
         * 2.打刻ページ表示テスト
         * 3.「退勤済」表示確認
         */

        // テスト用日時
        $testDate = Carbon::now()->startOfDay()->setHour(9);

        // 1.勤務開始
        $this->user->attendances()->create([
            'date' => $testDate,
            'clock_in' => $testDate,
            'clock_out' => $testDate->copy()->addHours(9),
        ]);

        // 2.打刻ページ表示テスト
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertViewIs('user.attendance-register');

        // 3.退勤済み表示確認
        $response->assertSee('退勤済');
    }


    /**
     * ID5 ステータス確認機能 
     * 出勤ボタンが正しく機能する
     * @return void
     */
    public function test_attendance_working_button_push(): void
    {
        /**
         * 1.打刻ページ表示テスト
         * 2.出勤ボタン表示テスト
         * 3.出勤ボタン押下
         * 4.データベース格納テスト
         * 5.「出勤中」表示テスト(リダイレクトのため注意)
         */

        // 時刻固定(データベース格納テスト時の時刻取得タイミングによるずれを回避)
        Carbon::setTestNow(Carbon::parse("2026-09-09 10:00:00"));

        // 1.打刻ページ表示テスト
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertViewIs('user.attendance-register');

        // 2.出勤ボタン表示テスト
        $response->assertSee('<button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>', false);

        // 3.出勤ボタン押下
        $response = $this->actingAs($this->user)->post('/attendance', [
            'action' => 'clock_in'
        ]);

        // 4.データベース格納テスト
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'clock_in' => Carbon::now(),
        ]);

        // 5.出勤中表示テスト(リダイレクトのため注意)
        $response->assertRedirect('/attendance');
        $response = $this->actingAs($this->user)
            ->get('/attendance');
        $response->assertSee('出勤中');
    }

    /**
     * ID6 出勤機能
     * 出勤は一日一回のみできる
     * @return void
     */
    public function test_attendance_working_button_not_display(): void
    {
        /**
         * 1.退勤済みユーザー作成
         * 2.打刻画面表示
         * 3.出勤ボタンが非表示テスト
         */

        // テスト用日時
        $testDate = Carbon::now()->startOfDay()->setHour(9);

        // 1.退勤済みユーザー作成
        $this->user->attendances()->create([
            'date' => $testDate,
            'clock_in' => $testDate,
            'clock_out' => $testDate->copy()->addHours(9),
        ]);

        // 2.打刻画面表示
        $response = $this->actingAs($this->user)->get('/attendance');

        // 3.出勤ボタン非表示テスト
        $response->assertDontSee('<button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>', false);
    }

    /**
     * ID6 出勤機能
     * 出勤時刻が勤怠一覧画面で確認できる
     * @return void
     */
    public function test_attendance_working_datetime_display(): void
    {
        /**
         * 1.出勤ボタン押下テスト
         * 2.勤怠一覧画面表示テスト
         * 3.出勤時間と表示される日付の一致確認
         */

        // 1.出勤ボタン押下テスト
        $response = $this->actingAs($this->user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        //2.勤怠一覧画面表示テスト
        $response = $this->actingAs($this->user)->get('/attendance/list');
        $response->assertViewIs('user.user-attendance-list');

        // 3.出勤時間と表示される日付の一致確認
        // 直前の出勤ボタン押下により記録されたレコードを取得
        $attandance = $this->user->attendances()
            ->latest('id')
            ->firstOrFail();

        // 日時出勤時間表示フォーマット
        $comparisonDate = $attandance->clock_in->isoFormat('MM月DD日(ddd)');
        $comparisonClockIn = $attandance->clock_in->format('H:i');

        // 勤怠レコードはテストデータとして一件のみなのでrecords[0]との比較で問題ない
        $records = $response->viewData('formattedAttendanceRecords');
        $this->assertEquals(
            $comparisonDate,
            $records[0]['date']
        );
        $this->assertEquals(
            $comparisonClockIn,
            $records[0]['clock_in']
        );

        // 実表示テスト
        $response->assertSeeInOrder([
            $comparisonDate,
            $comparisonClockIn
        ]);
    }

    /**
     * ID7 休憩機能
     * 休憩ボタンが正しく機能する
     * @return void
     */
    public function test_attendance_break_button_push(): void
    {
        /**
         * 1.出勤中ユーザー作成
         * 2.休憩入ボタン表示テスト
         * 3.休憩入ボタン押下テスト
         * 4.データベース格納テスト
         * 5.「出勤中」表示テスト(リダイレクトのため注意)
         */

        // 時刻固定(データベース格納テスト時の時刻取得タイミングによるずれを回避)
        Carbon::setTestNow(Carbon::parse("2026-09-09 10:00:00"));

        $now = Carbon::now();

        // 1.出勤中ユーザー作成
        $attendance = $this->user->attendances()->create([
            'date' => $now,
            'clock_in' => $now,
        ]);

        // 2.休憩入ボタン表示テスト
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--break-in" type="submit" name="action" value="break_in">休憩入</button>', false);

        // 3.休憩入ボタン押下
        $response = $this->actingAs($this->user)->post('/attendance', [
            'action' => 'break_in'
        ]);

        // 4.データベース格納テスト
        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_in' => $now,
        ]);

        // 5.休憩中表示テスト(リダイレクトのため注意)
        $response->assertRedirect('/attendance');
        $response = $this->actingAs($this->user)->get('/attendance');
        $response->assertSee('休憩中');
    }

    /**
     * ID7 休憩機能
     * 休憩ボタンが正しく機能する
     * 休憩は一日何回でもできる
     * 休憩戻ボタンが正しく機能する
     * 休憩戻は一日に何回でもできる
     * 休憩時刻が勤怠一覧画面で確認できる
     * @return void
     */
    public function test_can_complete_break_process(): void
    {
        /**
         * 手順
         *  1.出勤済みユーザー作成
         *  2.出勤中表示・および休憩入ボタン表示テスト
         *  3.休憩入ボタン押下
         *  4.休憩中表示・および休憩戻ボタン表示テスト
         *  5.休憩戻ボタン押下
         *  6.出勤中表示・および休憩入ボタン表示テスト
         *  7.休憩入ボタン押下
         *  8.休憩中表示・および休憩戻ボタン表示テスト
         *  9.休憩戻るボタン押下
         * 10.勤怠一覧画面表示
         * 11.休憩の表示テスト
         */

        $clock_in = Carbon::parse("2026-09-09 10:00:00");
        $break_in_01 = Carbon::parse("2026-09-09 12:00:00");
        $break_out_01 = Carbon::parse("2026-09-09 13:00:00");
        $break_in_02 = Carbon::parse("2026-09-09 18:00:00");
        $break_out_02 = Carbon::parse("2026-09-09 19:00:00");

        // 認証
        $this->actingAs($this->user);

        // 時間設定
        Carbon::setTestNow($clock_in);

        // 1.出勤済みユーザー作成
        $attendance = $this->user->attendances()->create([
            'date' => Carbon::now(),
            'clock_in' => Carbon::now()
        ]);

        // 2.出勤中表示・および休憩入ボタン表示テスト
        $response = $this->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--break-in" type="submit" name="action" value="break_in">休憩入</button>', false);
        $response->assertSee('出勤中');

        // 3.休憩入ボタン押下
        Carbon::setTestNow($break_in_01);
        $this->post('/attendance', ['action' => 'break_in']);

        // 4.休憩中表示・および休憩戻ボタン表示テスト
        $response = $this->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--break-out" type="submit" name="action" value="break_out">休憩戻</button>', false);
        $response->assertSee('休憩中');

        // 5.休憩戻ボタン押下
        Carbon::setTestNow($break_out_01);
        $this->post('/attendance', ['action' => 'break_out']);

        // 6.出勤中表示・および休憩入ボタン表示テスト
        $response = $this->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--break-in" type="submit" name="action" value="break_in">休憩入</button>', false);
        $response->assertSee('出勤中');

        // 7.休憩入ボタン押下
        Carbon::setTestNow($break_in_02);
        $this->post('/attendance', ['action' => 'break_in']);

        // 8.休憩中表示・および休憩戻ボタン表示テスト
        $response = $this->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--break-out" type="submit" name="action" value="break_out">休憩戻</button>', false);
        $response->assertSee('休憩中');

        // 9.休憩戻るボタン押下
        Carbon::setTestNow($break_out_02);
        $this->post('/attendance', ['action' => 'break_out']);

        // 10.勤怠一覧画面表示
        $response = $this->get('/attendance/list');

        // 11.休憩の表示テスト
        $records = $response->viewData('formattedAttendanceRecords');
        $this->assertEquals(
            $attendance->total_break_time,
            $records[0]['total_break_time']
        );

        // 直値でもテストしておく
        $minutes = $break_in_01->diffInMinutes($break_out_01) + $break_in_02->diffInMinutes($break_out_02);
        $h = (int) ((int) $minutes) / 60;
        $m = (int) ((int) $minutes) % 60;
        $break_time = sprintf('%d:%02d', $h, $m);

        // 直値による休憩時間比較もしておく
        $this->assertEquals(
            $break_time,
            $records[0]['total_break_time']
        );
    }

    /**
     * ID8 退勤機能
     * 退勤ボタンが正しく機能する
     * 退勤時刻が勤怠一覧画面で確認できる
     * @return void
     */
    public function test_can_complete_clock_out_process(): void
    {
        /**
         * 1.出勤済みのユーザーを作成
         * 2.退勤ボタンを押下
         * 3.勤怠一覧画面表示
         * 4.退勤時間表示テスト
         */

        $date = Carbon::parse("2026-09-09 10:00:00");
        $clock_in = $date->copy();
        $clock_out = Carbon::parse("2026-09-09 19:00:00");

        //　認証
        $this->actingAs($this->user);

        // 1.出勤済みのユーザーを作成
        Carbon::setTestNow($clock_in);
        $attendance = $this->user->attendances()->create([
            'date' => Carbon::now(),
            'clock_in' => Carbon::now(),
        ]);

        // 2.退勤ボタンを押下
        $response = $this->get('/attendance');
        $response->assertSee('<button class="attendance__button--submit--clock-out" type="submit" name="action" value="clock_out">退勤</button>', false);
        $response->assertSee('出勤中');

        // 時間を固定しておく
        Carbon::setTestNow($clock_out);
        $this->post('/attendance', ['action' => 'clock_out']);

        // 3.勤怠一覧画面表示
        $response = $this->get('/attendance/list');

        // 4.退勤時間表示テスト
        $records = $response->viewData('formattedAttendanceRecords');
        $this->assertEquals(
            $attendance->refresh()->clock_out->format('H:i'),
            $records[0]['clock_out']
        );

        // 実表示上でもテスト
        $response->assertSeeInOrder([
            $date->isoFormat('MM月DD日(ddd)'),//日付
            $clock_in->format('H:i'),       //出勤
            $clock_out->format('H:i'),      //退勤
        ]);
    }
}
