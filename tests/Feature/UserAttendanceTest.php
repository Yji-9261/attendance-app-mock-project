<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Database\Factories\BreakTimeFactory;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

use Carbon\Carbon;

class UserAttendanceTest extends TestCase
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
     * ID9 自分が行った勤怠情報が全て表示されている
     * @return void
     */
    public function test_can_get_current_month_attendance_list()
    {
        /**
         * 1. 勤怠情報が登録されたユーザーにログインする
         * 2. 勤怠一覧ページを開く
         * 3. 自分の勤怠情報がすべて表示されていることを確認する         
         */

        // テスト用勤怠情報生成
        $now = Carbon::now();
        $currentMonth = $now->copy();
        $this->createTestAttendance($currentMonth->copy()->startOfMonth()->setHour(8)->setMinute(45));
        $this->createTestAttendance($currentMonth->copy()->day(15)->setHour(9)->setMinute(0));
        // テスト用に休憩時間も生成
        $this->createTestBreakTimes(
            $this->createTestAttendance(
                $currentMonth->copy()->endOfMonth()->startOfDay()->setHour(9)->setMinute(15)
            )
        );

        // 2. 勤怠一覧ページを開く
        $response = $this->actingAs($this->user)->get("/attendance/list");

        // 月毎の表示テスト
        $this->assertMonthly($currentMonth, $response);
    }

    public function test_can_get_previous_month_attendance_list()
    {
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
        $response = $this->actingAs($this->user)->get("/attendance/list/?date={$preMonth}");

        // 月毎の表示テスト
        $this->assertMonthly($preMonth, $response);
    }

    public function test_can_get_next_month_attendance_list()
    {
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
        $response = $this->actingAs($this->user)->get("/attendance/list/?date={$nextMonth}");

        // 月毎の表示テスト
        $this->assertMonthly($nextMonth, $response);
    }

    /**
     * 月毎の表示物テスト
     * @param Carbon $datetime
     * @param mixed $response
     * @return void
     */
    private function assertMonthly(Carbon $datetime, $response)
    {
        // 期待されたビューかテスト
        $response->assertViewIs('user.user-attendance-list');

        /** 期待する月が表示されているかテスト */

        // 実表示テストと、受け渡されたデータ比較テストを行う
        $response->assertSee($datetime->format('Y/m'));
        $date = $response->viewData('date');
        $this->assertEquals($date->format('Y/m'), $datetime->format('Y/m'));


        /** 勤怠情報が全て表示されているかテスト */

        // bladeに渡されたデータ取得
        $formattedAttendanceRecords = $response->viewData('formattedAttendanceRecords');

        // 該当月の勤怠データ取得
        $monthlyAttendances = $this->user->attendances()
            ->whereYear('date', $datetime)
            ->whereMonth('date', $datetime)
            ->get();

        foreach ($formattedAttendanceRecords as $formattedAttendanceRecord) {
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
    }
    /**
     * 勤怠詳細テスト
     * @return void
     */
    public function test_can_get_attendance_detail()
    {
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
     * テスト用勤怠データ生成
     * @param Carbon $baseDatetime
     * @return Attendance|\Illuminate\Database\Eloquent\Collection<int, Attendance|\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model
     */
    private function createTestAttendance(Carbon $baseDatetime)
    {
        $date = $baseDatetime->copy();
        return Attendance::factory()->create([
            'user_id' => $this->user->id,
            'date' => $date->copy(),
            'clock_in' => $date->copy()->setHour(9),
            'clock_out' => $date->copy()->setHour(19),
        ]);
    }

    /**
     * テスト用休憩データ生成
     * @param Attendance $attendance
     * @return BreakTime|\Illuminate\Database\Eloquent\Collection<int, BreakTime|\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model
     */
    private function createTestBreakTimes(Attendance $attendance)
    {
        return BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'break_in' => $attendance->clock_in->copy()->addHours(1),
            'break_out' => $attendance->clock_in->copy()->addHours(2),
        ]);
    }
}
