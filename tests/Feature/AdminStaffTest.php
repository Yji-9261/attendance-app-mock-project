<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Illuminate\Testing\TestResponse;
use App\Models\User;
use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Carbon\Carbon;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-13 10:00:00'));

        $this->admin = User::factory()->create(['admin_status' => true]);
    }

    protected function tearDown(): void
    {
        // 時刻固定の解除
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * ID14 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる
     */
    public function test_can_get_all_staff_list(): void
    {
        /**
         * 1. 管理者でログインする
         * 2. スタッフ一覧ページを開く
         */

        // テスト用のユーザーを生成する
        $user1 = [
            "name" => "testA",
            "email" => "testA@test.com",
            "password" => "password",
        ];
        $this->createTestUsers($user1);

        $user2 = [
            "name" => "testB",
            "email" => "testB@test.com",
            "password" => "password",
        ];
        $this->createTestUsers($user2);

        $user3 = [
            "name" => "testC",
            "email" => "testC@test.com",
            "password" => "password",
        ];
        $this->createTestUsers($user3);


        // スタッフ一覧画面に表示されているかテスト
        $this->actingAs($this->admin)
            ->get('/admin/staff/list')
            ->assertOk()
            ->assertViewIs('admin.staff-list')

            // スタッフ1の表示確認
            ->assertSeeInOrder([
                $user1['name'],
                $user1['email'],
                '詳細'
            ])

            // スタッフ2の表示確認
            ->assertSeeInOrder([
                $user2['name'],
                $user2['email'],
                '詳細'
            ])

            // スタッフ3の表示確認
            ->assertSeeInOrder([
                $user3['name'],
                $user3['email'],
                '詳細',
            ]);
    }


    /**
     * ID14 ユーザーの勤怠情報が正しく表示される
     */
    public function test_can_view_staff_attendance_for_current_month(): void
    {
        /**
         * 1. 管理者ユーザーでログインする
         * 2. 選択したユーザーの勤怠一覧ページを開く
         */
        $month = Carbon::now()->startOfMonth();
        $user = $this->createTestUsers([
            'name' => 'testA',
            'email' => 'testA@test.com',
            'password' => 'password',
        ]);
        $this->createStaffAttendancesForMonth($user, $month);

        $response = $this->actingAs($this->admin)
            ->get("/admin/attendance/staff/{$user->id}");
        $this->assertStaffAttendanceForMonth($response, $user, $month);
    }

    /**
     * ID14 「前月」を押下した時に表示月の前月の情報が表示される
     */
    public function test_can_view_staff_attendance_for_previous_month(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠一覧ページを開く
         * 3. 「前月」ボタンを押す
         */
        $month = Carbon::now()->startOfMonth()->subMonth();
        $user = $this->createTestUsers([
            'name' => 'testA',
            'email' => 'testA@test.com',
            'password' => 'password',
        ]);
        $this->createStaffAttendancesForMonth($user, Carbon::now());
        $this->createStaffAttendancesForMonth($user, $month);

        // 当月の勤怠一覧を開き、前月リンクのURLを確認する
        $response = $this->actingAs($this->admin)
            ->get("/admin/attendance/staff/{$user->id}");
        $this->assertStaffAttendanceForMonth($response, $user, Carbon::now());
        $response->assertSee('href="?date=' . $month->format('Y-m') . '"', false);

        // 前月リンクのURLにアクセスする
        $response = $this->get("/admin/attendance/staff/{$user->id}?date=" . $month->format('Y-m'));
        $this->assertStaffAttendanceForMonth($response, $user, $month);
    }

    /**
     * ID14 「翌月」を押下した時に表示月の翌月の情報が表示される
     */
    public function test_can_view_staff_attendance_for_next_month(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠一覧ページを開く
         * 3. 「翌月」ボタンを押す
         */

        // ユーザーと勤怠データを2件作成
        $month = Carbon::now()->startOfMonth()->addMonth();
        $user = $this->createTestUsers([
            'name' => 'testA',
            'email' => 'testA@test.com',
            'password' => 'password',
        ]);
        $this->createStaffAttendancesForMonth($user, Carbon::now());
        $this->createStaffAttendancesForMonth($user, $month);

        // 当月の勤怠一覧を開き、翌月リンクのURLを確認する
        $response = $this->actingAs($this->admin)
            ->get("/admin/attendance/staff/{$user->id}");
        $this->assertStaffAttendanceForMonth($response, $user, Carbon::now());
        $response->assertSee('href="?date=' . $month->format('Y-m') . '"', false);

        // 翌月リンクのURLにアクセスする
        $response = $this->actingAs($this->admin)->get("/admin/attendance/staff/{$user->id}?date=" . $month->format('Y-m'));
        $this->assertStaffAttendanceForMonth($response, $user, $month);
    }

    /**
     * ID14 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     */
    public function test_can_open_staff_attendance_detail_from_monthly_list(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 勤怠一覧ページを開く
         * 3. 「詳細」ボタンを押下する
         */
        $month = Carbon::now()->startOfMonth();
        $user = $this->createTestUsers([
            'name' => 'testA',
            'email' => 'testA@test.com',
            'password' => 'password',
        ]);
        $this->createStaffAttendancesForMonth($user, $month);

        // 管理者でログインして、対象スタッフの月次勤怠一覧を開く
        $response = $this->actingAs($this->admin)
            ->get("/admin/attendance/staff/{$user->id}");
        $this->assertStaffAttendanceForMonth($response, $user, $month);

        foreach ($user->attendances()->with('breaktimes')->get() as $attendance) {
            // 各日の詳細リンクが表示され、そのURLから詳細画面を開けることを確認
            $detailUrl = url('/attendance/' . $attendance->id);
            $response->assertSee('href="' . $detailUrl . '"', false);

            $detailResponse = $this->get($detailUrl)
                ->assertOk()
                ->assertViewIs('admin.admin-detail')
                ->assertViewHas('attendanceRecord', fn($record) => $record['id'] === $attendance->id);

            // 遷移先が選択した日の勤怠であることを、表示内容でも確認する
            $expected = [
                $user->name,
                $attendance->date->year . "年",
                $attendance->date->format('n月j日'),
                $attendance->clock_in->format('H:i'),
                $attendance->clock_out->format('H:i'),
            ];
            foreach ($attendance->breaktimes as $breaktime) {
                $expected[] = $breaktime->break_in->format('H:i');
                $expected[] = $breaktime->break_out->format('H:i');
            }
            $detailResponse->assertSeeInOrder($expected);
        }
    }



    /** 指定月の月初・月末に、勤怠と休憩を作成する。 */
    private function createStaffAttendancesForMonth(User $user, Carbon $month): void
    {
        $date1 = $month->copy()->startOfMonth()->hour(9);
        $date2 = $month->copy()->endOfMonth()->startOfDay()->hour(10);

        $user->attendances()->create([
            'date' => $date1,
            'clock_in' => $date1,
            'clock_out' => $date1->copy()->hour(18),
        ])->breaktimes()->create([
                    'break_in' => $date1->copy()->hour(12),
                    'break_out' => $date1->copy()->hour(13),
                ]);

        $user->attendances()->create([
            'date' => $date2,
            'clock_in' => $date2,
            'clock_out' => $date2->copy()->hour(22),
        ])->breaktimes()->createMany([
                    [
                        'break_in' => $date2->copy()->hour(12),
                        'break_out' => $date2->copy()->hour(13),
                    ],
                    [
                        'break_in' => $date2->copy()->hour(18),
                        'break_out' => $date2->copy()->hour(19),
                    ],
                ]);
    }

    /** 指定ユーザーの対象月の勤怠が表示されることを確認する。 */
    private function assertStaffAttendanceForMonth(TestResponse $response, User $user, Carbon $month): void
    {
        $response->assertOk()
            ->assertViewIs('admin.staff-attendance-list')
            ->assertSee("{$user->name}さんの勤怠")
            ->assertSeeInOrder(['前月', $month->format('Y/m'), '翌月']);

        foreach ($user->attendances()->get() as $attendance) {
            if ($attendance->date->format('Y-m') !== $month->format('Y-m')) {
                // 別の月の勤怠が混ざっていないことを確認
                $response->assertDontSee('href="' . url('/attendance/' . $attendance->id) . '"', false);
                continue;
            }

            // 表示テスト
            $response->assertSeeInOrder([
                $attendance->date->isoFormat('MM月DD日'),
                $attendance->clock_in->format('H:i'),
                $attendance->clock_out->format('H:i'),
                $attendance->total_break_time,
                $attendance->total_time,
                url('/attendance/' . $attendance->id),
                '詳細',
            ]);
        }
    }

    /**
     * テスト用のユーザー生成メソッド
     * @param array $userData
     * @param array $attendanceData
     * @param array $breakTimesData
     */
    private function createTestUsers(
        array $userData,
        ?array $attendanceData = null,
        ?array $breakTimesData = null
    ) {
        $user = User::factory()->create($userData);

        if ($attendanceData) {
            $attendance = $user->attendances()->create($attendanceData);

            if ($breakTimesData) {
                $attendance->breaktimes()->create($breakTimesData);
            }
        }

        return $user;
    }
}
