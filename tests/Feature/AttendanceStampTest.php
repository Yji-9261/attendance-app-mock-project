<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStampTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証可能ユーザー
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
     * 現在の日時情報がUIと同じ形式で出力されている
     */
    public function testAttendanceDatetimeGet(): void
    {
        // 1.       勤怠打刻画面を開く
        // 2.       画面に表示されている日時情報を確認する
        // result.  画面上に表示されている日時が現在の日時と一致する

        $now = Carbon::now();
        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertViewIs('user.attendance-register')
            ->assertSee($now->isoFormat('YYYY年MM月DD日(ddd)'))
            ->assertSee($now->format('H:i'));
    }

    /**
     * 勤務外の場合、勤怠ステータスが正しく表示される
     */
    public function testAttendanceStatusNotWorking(): void
    {
        // 1. ステータスが勤務外のユーザーにログインする
        // 2. 勤怠打刻画面を開く
        // 3. 画面に表示されているステータスを確認する
        // result.  画面上に表示されているステータスが「勤務外」となる

        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertViewIs('user.attendance-register')
            ->assertSee('勤務外');
    }

    /**
     * 出勤中の場合、勤怠ステータスが正しく表示される
     */
    public function testAttendanceStatusWorking(): void
    {
        // 1. ステータスが出勤中のユーザーにログインする
        // 2. 勤怠打刻画面を開く
        // 3. 画面に表示されているステータスを確認する
        // result.  画面上に表示されているステータスが「出勤中」となる

        $now = Carbon::now();
        $this->user->attendances()->create([
            'date' => $now,
            'clock_in' => $now,
        ]);

        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertViewIs('user.attendance-register')
            ->assertSee('出勤中');
    }

    /**
     * 休憩中の場合、勤怠ステータスが正しく表示される
     */
    public function testAttendanceStatusBreaking(): void
    {
        // 1. ステータスが休憩中のユーザーにログインする
        // 2. 勤怠打刻画面を開く
        // 3. 画面に表示されているステータスを確認する
        // result.  画面上に表示されているステータスが「休憩中」となる

        $now = Carbon::now();
        $attendance = $this->user->attendances()->create([
            'date' => $now,
            'clock_in' => $now,
        ]);
        $attendance->breaktimes()->create([
            'break_in' => $now,
        ]);

        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertViewIs('user.attendance-register')
            ->assertSee('休憩中');
    }

    /**
     * 退勤済の場合、勤怠ステータスが正しく表示される
     */
    public function testAttendanceStatusFinshed(): void
    {
        // 1. ステータスが退勤済のユーザーにログインする
        // 2. 勤怠打刻画面を開く
        // 3. 画面に表示されているステータスを確認する
        // result.  画面上に表示されているステータスが「退勤済」となる

        // 退勤状態の勤怠を生成する
        $testDate = Carbon::now()->startOfDay()->setHour(9);
        $this->user->attendances()->create([
            'date' => $testDate,
            'clock_in' => $testDate,
            'clock_out' => $testDate->copy()->hour(18),
        ]);

        $this->actingAs($this->user)->get('/attendance')
            ->assertViewIs('user.attendance-register')
            ->assertSee('退勤済');
    }

    /**
     * 出勤ボタンが正しく機能する
     */
    public function testAttendanceWorkingButtonPush(): void
    {
        // 1. ステータスが勤務外のユーザーにログインする
        // 2. 画面に「出勤」ボタンが表示されていることを確認する
        // 3. 出勤の処理を行う
        // result.  画面上に「出勤」ボタンが表示され、処理後に画面上に表示されるステータスが「勤務中」になる

        // 時刻固定(データベース格納テスト時の時刻取得タイミングによるずれを回避)
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));

        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertViewIs('user.attendance-register')
            ->assertSee('<button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>', false);

        $this->post('/attendance', [
            'action' => 'clock_in',
        ])->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'clock_in' => Carbon::now(),
        ]);

        $this->get('/attendance')->assertSee('出勤中');
    }

    /**
     * 出勤は一日一回のみできる
     */
    public function testAttendanceWorkingButtonNotDisplay(): void
    {
        // 1. ステータスが退勤済であるユーザーにログインする
        // 2. 勤務ボタンが表示されないことを確認する
        // result.  画面上に「出勤」ボタンが表示されない

        $testDate = Carbon::now()->startOfDay()->setHour(9);

        $this->user->attendances()->create([
            'date' => $testDate,
            'clock_in' => $testDate,
            'clock_out' => $testDate->copy()->hour(18),
        ]);

        $this->actingAs($this->user)
            ->get('/attendance')
            ->assertDontSee('<button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>', false);
    }

    /**
     * 出勤時刻が勤怠一覧画面で確認できる
     */
    public function testAttendanceWorkingDatetimeDisplay(): void
    {
        // 1.       ステータスが勤務外のユーザーにログインする
        // 2.       出勤の処理を行う
        // 3.       勤怠一覧画面から出勤の日付を確認する
        // result.  勤怠一覧画面に出勤時刻が正確に記録されている

        // テスト用に時間固定
        Carbon::setTestNow('2027-01-01 01:01:00');
        $testDateTime = Carbon::now();

        $this->actingAs($this->user)
            ->post('/attendance', [
                'action' => 'clock_in',
            ]);

        // たった今登録された勤怠データの日付と出勤時間を取得
        $this->assertDatabaseHas('attendances', [
            'date' => $testDateTime,
            'clock_in' => $testDateTime,
        ]);
        $comparisonDate = $testDateTime->isoFormat('MM月DD日(ddd)');
        $comparisonClockIn = $testDateTime->format('H:i');

        // 日付と出勤時間を実表示、データ検証する
        $records = $this->get('/attendance/list')
            ->assertViewIs('user.user-attendance-list')
            ->assertSee($comparisonClockIn)
            ->assertSee($comparisonDate)
            ->viewData('formattedAttendanceRecords');

        // データ検証
        // 勤怠レコードの生成は月初で固定しているので$recordsは最初のデータ参照で良い
        $this->assertEquals(
            $comparisonDate,
            $records[0]['date']
        );
        $this->assertEquals(
            $comparisonClockIn,
            $records[0]['clock_in']
        );
    }

    /**
     *     休憩ボタンが正しく機能する
     */
    public function testAttendanceBreakButtonPush(): void
    {
        // 1.       ステータスが出勤中のユーザーにログインする
        // 2.       画面に「休憩入」ボタンが表示されていることを確認する
        // 3.       休憩の処理を行う
        // result.  画面上に「休憩入」ボタンが表示され、処理後に画面上に表示されるステータスが「休憩中」になる

        // 時刻固定(データベース格納テスト時の時刻取得タイミングによるずれを回避)
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));

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
            'action' => 'break_in',
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
     * 休憩は一日に何回でもできる
     1. ステータスが出勤中であるユーザーにログインする
        2. 休憩入と休憩戻の処理を行う
        3. 「休憩入」ボタンが表示されることを確認する"	画面上に「休憩入」ボタンが表示される

        休憩戻ボタンが正しく機能する
        1. ステータスが出勤中であるユーザーにログインする
        2. 休憩入の処理を行う
        3. 休憩戻の処理を行う
        休憩戻ボタンが表示され、処理後にステータスが「出勤中」に変更される

        休憩戻は一日に何回でもできる
        1. ステータスが出勤中であるユーザーにログインする
        2. 休憩入と休憩戻の処理を行い、再度休憩入の処理を行う
        3. 「休憩戻」ボタンが表示されることを確認する
        画面上に「休憩戻」ボタンが表示される

        休憩時刻が勤怠一覧画面で確認できる
        1. ステータスが勤務中のユーザーにログインする
        2. 休憩入と休憩戻の処理を行う
        3. 勤怠一覧画面から休憩の日付を確認する
        勤怠一覧画面に休憩時刻が正確に記録されている
     */
    public function testCanCompleteBreakProcess(): void
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
        $clock_in = Carbon::parse('2026-09-01 10:00:00');
        $break_in_01 = Carbon::parse('2026-09-01 12:00:00');
        $break_out_01 = Carbon::parse('2026-09-01 13:00:00');
        $break_in_02 = Carbon::parse('2026-09-01 18:00:00');
        $break_out_02 = Carbon::parse('2026-09-01 19:00:00');

        // 認証
        $this->actingAs($this->user);

        // 時間設定
        Carbon::setTestNow($clock_in);

        // 1.出勤済みユーザー作成
        $attendance = $this->user->attendances()->create([
            'date' => Carbon::now(),
            'clock_in' => Carbon::now(),
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
        // 勤怠レコードの生成は月初で固定しているので$recordsは最初のデータ参照で良い
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
        // 勤怠レコードの生成は月初で固定しているので$recordsは最初のデータ参照で良い
        $this->assertEquals(
            $break_time,
            $records[0]['total_break_time']
        );
    }

    /**
     * ID8 退勤機能
     * 退勤ボタンが正しく機能する
     * 退勤時刻が勤怠一覧画面で確認できる
     */
    public function testCanCompleteClockOutProcess(): void
    {
        /**
         * 1.出勤済みのユーザーを作成
         * 2.退勤ボタンを押下
         * 3.勤怠一覧画面表示
         * 4.退勤時間表示テスト
         */
        $date = Carbon::parse('2026-09-01 10:00:00');
        $clock_in = $date->copy();
        $clock_out = Carbon::parse('2026-09-01 19:00:00');

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
        // 勤怠レコードの生成は月初で固定しているので$recordsは最初のデータ参照で良い
        $records = $response->viewData('formattedAttendanceRecords');
        $this->assertEquals(
            $attendance->refresh()->clock_out->format('H:i'),
            $records[0]['clock_out']
        );

        // 実表示上でもテスト
        $response->assertSeeInOrder([
            $date->isoFormat('MM月DD日(ddd)'), // 日付
            $clock_in->format('H:i'),       // 出勤
            $clock_out->format('H:i'),      // 退勤
        ]);
    }
}
