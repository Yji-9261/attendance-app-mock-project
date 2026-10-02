<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAttendanceTest extends TestCase
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
     * 自分が行った勤怠情報が全て表示されている
     * 勤怠一覧画面に遷移した際に現在の月が表示される
     */
    public function testCanGetCurrentMonthAttendanceList(): void
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        // 2. 勤怠一覧ページを開く
        // 3. 自分の勤怠情報がすべて表示されていることを確認する
        // 自分の勤怠情報がすべて表示されている
        // 現在の月が表示されている

        // テスト用勤怠情報生成
        $currentStartOfMonth = Carbon::now()->copy()->startOfMonth();
        $this->createTestAttendance($currentStartOfMonth->copy()->hour(9));
        $this->createTestAttendance($currentStartOfMonth->copy()->day(15)->hour(9));

        // テスト用に休憩時間も生成
        $this->createTestBreakTimes(
            $this->createTestAttendance(
                $currentStartOfMonth->copy()->endOfMonth()->startOfDay()->setHour(9)->setMinute(15)
            )
        );

        // 2. 勤怠一覧ページを開く
        $response = $this->actingAs($this->user)->get('/attendance/list');

        // 月毎の表示テスト
        $this->assertMonthly($currentStartOfMonth, $response);
    }

    /**
     * 「前月」を押下した時に表示月の前月の情報が表示される
     */
    public function testCanGetPreviousMonthAttendanceList(): void
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠一覧ページを開く
        // 3. 「前月」ボタンを押す
        // 前月の情報が表示されている

        // テスト用勤怠情報生成
        $now = Carbon::now();
        $preMonth = $now->startOfMonth()->subMonth();
        $this->createTestAttendance($preMonth->copy()->startOfMonth()->setHour(8)->setMinute(45));
        $this->createTestAttendance($preMonth->copy()->day(15)->setHour(9)->setMinute(0));

        // テスト用に休憩時間も生成
        $this->createTestBreakTimes(
            $this->createTestAttendance(
                $preMonth->copy()->endOfMonth()->startOfDay()->setHour(9)->setMinute(15)
            )
        );

        // 前月勤怠一覧画面表示
        $date = $preMonth->format('Y-m');
        $response = $this->actingAs($this->user)->get("/attendance/list/?date={$date}");

        // 月毎の表示テスト
        $this->assertMonthly($preMonth, $response);
    }

    /**
     * 「翌月」を押下した時に表示月の前月の情報が表示される
     */
    public function testCanGetNextMonthAttendanceList(): void
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠一覧ページを開く
        // 3. 「翌月」ボタンを押す
        // 翌月の情報が表示されている

        // テスト用勤怠情報生成
        $now = Carbon::now();
        $nextMonth = $now->startOfMonth()->addMonth();
        $this->createTestAttendance($nextMonth->copy()->startOfMonth()->setHour(8)->setMinute(45));
        $this->createTestAttendance($nextMonth->copy()->day(15)->setHour(9)->setMinute(0));

        // 休憩時間も生成
        $this->createTestBreakTimes(
            $this->createTestAttendance(
                $nextMonth->copy()->endOfMonth()->startOfDay()->setHour(9)->setMinute(15)
            )
        );

        // 翌月勤怠一覧画面表示
        $date = $nextMonth->format('Y-m');
        $response = $this->actingAs($this->user)->get("/attendance/list/?date={$date}");

        // 月毎の表示テスト
        $this->assertMonthly($nextMonth, $response);
    }

    /**
     * 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     *
     * @return void
     */
    public function testCanGetAttendanceDetail()
    {
        // 1. 勤怠情報が登録されたユーザーにログインをする
        // 2. 勤怠一覧ページを開く
        // 3. 「詳細」ボタンを押下す"
        // その日の勤怠詳細画面に遷移する

        $attendance = $this->createTestAttendance(Carbon::now()->day(15)->hour(10));

        // 休憩を２つ生成
        $attendance->breaktimes()->create([
            'break_in' => Carbon::now()->hour(12),
            'break_out' => Carbon::now()->hour(13),
        ]);
        $attendance->breaktimes()->create([
            'break_in' => Carbon::now()->hour(15),
            'break_out' => Carbon::now()->hour(16),
        ]);

        $attendance_id = $attendance->id;

        // 詳細ボタン押下時の遷移先をテスト
        $response = $this->actingAs($this->user)->get("/attendance/{$attendance_id}");
        $response->assertViewIs('user.user-detail');

        // ユーザー名称テスト
        $response->assertSee('value="' . $this->user->name . '"', false);

        // 日付テスト
        $response->assertSee(
            '<input class="form__input form__input--date" type="text" value="'
            . $attendance->date->year .
            '年" readonly>',
            false
        );
        $response->assertSee(
            '<input class="form__input form__input--date" type="text" name="new_date" value="'
            . $attendance->date->format('n月j日') .
            '" readonly>',
            false
        );

        // 出勤・退勤テスト
        $response->assertSee(
            '<input class="form__input" id="new_clock_in" type="text" name="new_clock_in" value="'
            . $attendance->clock_in->format('H:i') .
            '">',
            false
        );
        $response->assertSee(
            'input class="form__input" type="text" name="new_clock_out" value="'
            . $attendance->clock_out->format('H:i') .
            '">',
            false
        );

        // 休憩1テスト
        $response->assertSee(
            '<input class="form__input" type="text" name="new_break_in[0]" value="'
            . $attendance->breaktimes[0]->break_in->format('H:i') .
            '">',
            false
        );
        $response->assertSee(
            '<input class="form__input" type="text" name="new_break_out[0]" value="'
            . $attendance->breaktimes[0]->break_out->format('H:i') .
            '">',
            false
        );

        // 休憩2テスト
        $response->assertSee(
            '<input class="form__input" type="text" name="new_break_in[1]" value="'
            . $attendance->breaktimes[1]->break_in->format('H:i') .
            '">',
            false
        );
        $response->assertSee(
            '<input class="form__input" type="text" name="new_break_out[1]" value="'
            . $attendance->breaktimes[1]->break_out->format('H:i') .
            '">',
            false
        );
    }

    /**
     * 月毎の表示物テスト
     *
     * @param  mixed  $response
     */
    private function assertMonthly(Carbon $datetime, $response): void
    {
        // 期待されたビューかテスト
        $response->assertViewIs('user.user-attendance-list');

        // 期待する月が表示されているかテスト
        $date = $response->assertSee($datetime->format('Y/m'))->viewData('date');
        $this->assertEquals($date->format('Y/m'), $datetime->format('Y/m'));

        // bladeに渡されたデータ取得
        $formattedAttendanceRecords = $response->viewData('formattedAttendanceRecords');

        // 該当月の勤怠データ取得
        $monthlyAttendances = $this->user->attendances()
            ->whereYear('date', $datetime)
            ->whereMonth('date', $datetime)
            ->get();

        // 勤怠データは一件のみ生成なので最初のレコードを参照する
        $formattedAttendanceRecord = $formattedAttendanceRecords[0];

        $attendance_id = $formattedAttendanceRecord['id'];
        $record = $monthlyAttendances->find($attendance_id);

        // 比較用に表示フォーマット生成
        $compareDate = $record->date->isoFormat('MM月DD日(ddd)');
        $compareClockIn = $record->clock_in->format('H:i');
        $compareClockOut = $record->clock_out->format('H:i');

        // データ比較テスト
        $this->assertEquals($formattedAttendanceRecord['date'], $compareDate);
        $this->assertEquals($formattedAttendanceRecord['clock_in'], $compareClockIn);
        $this->assertEquals($formattedAttendanceRecord['clock_out'], $compareClockOut);
        $this->assertEquals($formattedAttendanceRecord['total_time'], $record->totaltime);
        $this->assertEquals($formattedAttendanceRecord['total_break_time'], $record->total_break_time);

        // 実表示テストも行う
        $response->assertSeeInOrder([
            $compareDate,
            $compareClockIn,
            $compareClockOut,
            $record->total_break_time,
            $record->totaltime,
        ]);

        // 詳細ボタン押下時の遷移先をテスト
        $responseDetail = $this->get("/attendance/{$attendance_id}");
        $responseDetail->assertViewIs('user.user-detail');
    }

    /**
     * テスト用勤怠データ生成
     *
     * @return Attendance|Collection<int, Attendance|Model>|Model
     */
    private function createTestAttendance(Carbon $baseDatetime): Attendance
    {
        return Attendance::factory()->create([
            'user_id' => $this->user->id,
            'date' => $baseDatetime,
            'clock_in' => $baseDatetime->copy()->setHour(9),
            'clock_out' => $baseDatetime->copy()->setHour(19),
        ]);
    }

    /**
     * テスト用休憩データ生成
     *
     * @return BreakTime|Collection<int, BreakTime|Model>|Model
     */
    private function createTestBreakTimes(Attendance $attendance): BreakTime
    {
        return BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'break_in' => $attendance->clock_in->copy()->addHours(1),
            'break_out' => $attendance->clock_in->copy()->addHours(2),
        ]);
    }
}
