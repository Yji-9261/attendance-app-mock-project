<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'テストユーザー',
        ]);

        $date = Carbon::now();

        $this->attendance = $this->user->attendances()->create([
            'date' => $date,
            'clock_in' => $date->copy()->setTime(9, 0),
            'clock_out' => $date->copy()->setTime(18, 0),
        ]);

        $this->attendance->breaktimes()->createMany([
            [
                'break_in' => $date->copy()->setTime(12, 0),
                'break_out' => $date->copy()->setTime(13, 0),
            ],
            [
                'break_in' => $date->copy()->setTime(15, 0),
                'break_out' => $date->copy()->setTime(15, 30),
            ],
        ]);
    }

    /**
     * 勤怠詳細画面の「名前」がログインユーザーの氏名になっている
     */
    public function testAttendanceDetailShowsAuthenticatedUserName(): void
    {
        /**
         * 1. 勤怠情報が登録されたユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 名前欄を確認する 
         * 名前がログインユーザーの名前になっている
         */

        // 勤怠情報が登録されたユーザーとして勤怠詳細ページを開く
        $response = $this->actingAs($this->user)
            ->get("/attendance/{$this->attendance->id}");

        // 名前欄にログインユーザーの名前が表示されている
        $response
            ->assertOk()
            ->assertViewIs('user.user-detail')
            ->assertSee(
                'name="name" value="' . $this->user->name . '"',
                false
            );
    }

    /**
     * 勤怠詳細画面の「日付」が選択した日付になっている
     */
    public function testAttendanceDetailShowsSelectedDate(): void
    {
        /** 
         * 1. 勤怠情報が登録されたユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 日付欄を確認する
         * 日付が選択した日付になっている
         */

        // 勤怠情報が登録されたユーザーとして勤怠詳細ページを開く
        $response = $this->actingAs($this->user)
            ->get("/attendance/{$this->attendance->id}");

        // 日付欄に選択した勤怠の日付が表示されている
        $response
            ->assertOk()
            ->assertViewIs('user.user-detail')
            ->assertSee(
                'value="' . $this->attendance->date->year . '年"',
                false
            )
            ->assertSee(
                'name="new_date" value="' . $this->attendance->date->format('n月j日') . '"',
                false
            );
    }

    /**
     * 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している
     */
    public function testAttendanceDetailShowsRecordedClockTimes(): void
    {
        /**
         * 1. 勤怠情報が登録されたユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 出勤・退勤欄を確認する 
         * 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している
         */

        // 勤怠情報が登録されたユーザーとして勤怠詳細ページを開く
        $response = $this->actingAs($this->user)
            ->get("/attendance/{$this->attendance->id}");

        // 出勤・退勤欄に登録済みの打刻時刻が表示されている
        $response
            ->assertOk()
            ->assertViewIs('user.user-detail')
            ->assertSee(
                'name="new_clock_in" value="' . $this->attendance->clock_in->format('H:i') . '"',
                false
            )
            ->assertSee(
                'name="new_clock_out" value="' . $this->attendance->clock_out->format('H:i') . '"',
                false
            );
    }

    /**
     * 「休憩」にて記されている時間がログインユーザーの打刻と一致している
     */
    public function testAttendanceDetailShowsRecordedBreakTimes(): void
    {
        /**
         * 1. 勤怠情報が登録されたユーザーにログインをする
         * 2. 勤怠詳細ページを開く
         * 3. 休憩欄を確認する 
         * 「休憩」にて記されている時間がログインユーザーの打刻と一致している
         */

        // 勤怠情報が登録されたユーザーとして勤怠詳細ページを開く
        $response = $this->actingAs($this->user)
            ->get("/attendance/{$this->attendance->id}");

        $response
            ->assertOk()
            ->assertViewIs('user.user-detail');

        // すべての休憩欄に登録済みの開始・終了時刻が表示されている
        foreach ($this->attendance->breaktimes as $index => $breakTime) {
            $response
                ->assertSee(
                    "name=\"new_break_in[{$index}]\" value=\"{$breakTime->break_in->format('H:i')}\"",
                    false
                )
                ->assertSee(
                    "name=\"new_break_out[{$index}]\" value=\"{$breakTime->break_out->format('H:i')}\"",
                    false
                );
        }
    }
}
