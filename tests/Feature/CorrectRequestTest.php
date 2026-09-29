<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrectRequestTest extends TestCase
{
    use RefreshDatabase;

    const DATE = '2026-01-01';

    const CLOCK_IN = '09:00';

    const CLOCK_OUT = '18:00';

    const BREAK_IN = '12:00';

    const BREAK_OUT = '13:00';

    const NEW_CLOCK_IN = '10:00';

    const NEW_CLOCK_OUT = '19:00';

    const NEW_BREAK_IN = '12:30';

    const NEW_BREAK_OUT = '13:30';

    const COMMENT = '備考';

    /**
     * 認証可能ユーザー
     */
    protected User $user;

    protected User $admin;

    protected Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::DATE);

        // 一般ユーザー登録
        $this->user = User::factory()->create();
        // 認証
        $this->actingAs($this->user);

        // 管理者ユーザー登録
        $this->admin = User::factory()
            ->create([
                'admin_status' => true,
            ]);

        // 勤怠レコード作成
        $this->attendance = $this->user
            ->attendances()
            ->create([
                'date' => self::DATE,
                'clock_in' => self::CLOCK_IN,
                'clock_out' => self::CLOCK_OUT,
            ]);

        $this->attendance->breaktimes()->create([
            'break_in' => self::BREAK_IN,
            'break_out' => self::BREAK_OUT,
        ]);
    }

    protected function tearDown(): void
    {
        // 時刻固定の解除
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function postAttendanceUpdate(
        $newClockIn = self::NEW_CLOCK_IN,
        $newClockOut = self::NEW_CLOCK_OUT,
        $newBreakIn = self::NEW_BREAK_IN,
        $newBreakOut = self::NEW_BREAK_OUT,
        $comment = self::COMMENT,
    ) {
        // ポストデータ
        $postRequest = [
            'new_clock_in' => $newClockIn,
            'new_clock_out' => $newClockOut,
            'new_break_in' => [$newBreakIn],
            'new_break_out' => [$newBreakOut],
            'comment' => $comment,
        ];

        return $this->post("/attendance/{$this->attendance->id}", $postRequest);
    }

    /**
     * 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
     *
     * @return void
     */
    public function testExpectedValidationMessageForClockInAfterClockOut()
    {
        // 退勤時間よりも早く設定する
        $this->postAttendanceUpdate(newClockIn: '23:59')
            ->assertSessionHasErrors([
                'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);
    }

    /**
     * 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
     *
     * @return void
     */
    public function testExpectedValidationMessageForBreakInAfterClockOut()
    {
        $this->postAttendanceUpdate(newBreakIn: '23:59')
            ->assertSessionHasErrors([
                'new_break_in.0' => '休憩時間が不適切な値です',
            ]);
    }

    /**
     * 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
     *
     * @return void
     */
    public function testExpectedValidationMessageForBreakOutAfterClockOut()
    {
        $this->postAttendanceUpdate(newBreakOut: '23:59')
            ->assertSessionHasErrors([
                'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
            ]);
    }

    /**
     * 備考欄が未入力の場合のエラーメッセージが表示される
     *
     * @return void
     */
    public function testExpectedValidationMessageForCommentRequired()
    {
        $this->postAttendanceUpdate(comment: '')
            ->assertSessionHasErrors([
                'comment' => '備考を記入してください',
            ]);
    }

    /**
     * 修正申請処理が実行される
     */
    public function testUserCanSubmitCorrectionRequestAndAdminCanViewIt(): void
    {
        /**
         * 1. 勤怠情報が登録されたユーザーにログインをする
         * 2. 勤怠詳細を修正し保存処理をする
         * 3. 管理者ユーザーで承認画面と申請一覧画面を確認する
         * result. 修正申請が実行され、管理者の承認画面と申請一覧画面に表示される
         */

        // 2. 勤怠詳細を修正し保存処理をする
        $this->get("/attendance/{$this->attendance->id}")
            ->assertOk()
            ->assertViewIs('user.user-detail');

        $this->postAttendanceUpdate()
            ->assertSessionHasNoErrors()
            ->assertRedirect("/attendance/{$this->attendance->id}");
        $application = $this->attendance->applications()->firstOrFail();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'attendance_id' => $this->attendance->id,
            'new_clock_in' => Carbon::parse(self::DATE . ' ' . self::NEW_CLOCK_IN)->toDateTimeString(),
            'new_clock_out' => Carbon::parse(self::DATE . ' ' . self::NEW_CLOCK_OUT)->toDateTimeString(),
            'comment' => self::COMMENT,
            'approval_status' => '承認待ち',
            'application_date' => Carbon::now()->toDateTimeString(),
        ]);
        $this->assertSame(1, $application->breakapplications()->count());
        $this->assertDatabaseHas('break_applications', [
            'application_id' => $application->id,
            'break_in' => Carbon::parse(self::DATE . ' ' . self::NEW_BREAK_IN)->toDateTimeString(),
            'break_out' => Carbon::parse(self::DATE . ' ' . self::NEW_BREAK_OUT)->toDateTimeString(),
        ]);

        // 承認前は元の勤怠・休憩が変更されない
        $this->assertDatabaseHas('attendances', [
            'id' => $this->attendance->id,
            'clock_in' => Carbon::parse(self::DATE . ' ' . self::CLOCK_IN)->toDateTimeString(),
            'clock_out' => Carbon::parse(self::DATE . ' ' . self::CLOCK_OUT)->toDateTimeString(),
        ]);
        $this->assertSame(1, $this->attendance->breaktimes()->count());
        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $this->attendance->id,
            'break_in' => Carbon::parse(self::DATE . ' ' . self::BREAK_IN)->toDateTimeString(),
            'break_out' => Carbon::parse(self::DATE . ' ' . self::BREAK_OUT)->toDateTimeString(),
        ]);

        // 3. 管理者ユーザーで承認画面と申請一覧画面を確認する
        $this->actingAs($this->admin);
        $this->get("/stamp_correction_request/approve/{$application->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-application-detail')
            ->assertSee($this->user->name)
            ->assertSee(self::COMMENT)
            ->assertSee(Carbon::parse(self::NEW_CLOCK_IN)->format('G:i'))
            ->assertSee(Carbon::parse(self::NEW_CLOCK_OUT)->format('G:i'))
            ->assertSee(Carbon::parse(self::NEW_BREAK_IN)->format('H:i'))
            ->assertSee(Carbon::parse(self::NEW_BREAK_OUT)->format('H:i'));

        $response = $this->get('/stamp_correction_request/list');
        $response->assertOk()->assertViewIs('admin.admin-application-list');
        $this->assertTabContainsRequests($response->getContent(), 'content1', [$application], true);
    }

    /**
     * ID11「承認待ち」にログインユーザーが行った申請が全て表示されていること
     */
    public function testPendingTabDisplaysAllOwnCorrectionRequests(): void
    {
        /**
         * 1. 勤怠情報が登録されたユーザーにログインをする
         * 2. 勤怠詳細を修正し保存処理をする
         * 3. 申請一覧画面を確認する
         * * result. 申請一覧に自分の申請が全て表示されている
         */
        $applications = $this->submitRequestsForUser($this->user);
        $otherUser = User::factory()->create();
        $otherApplications = $this->submitRequestsForUser($otherUser);

        $this->actingAs($this->user);
        $response = $this->get('/stamp_correction_request/list');
        $response->assertOk()->assertViewIs('user.user-application-list');
        $this->assertTabContainsRequests($response->getContent(), 'content1', $applications);
        $this->assertTabContainsRequests($response->getContent(), 'content2', []);
        foreach ($otherApplications as $application) {
            $response->assertDontSee($application->comment);
        }
    }

    /**
     * ID11「承認済み」に管理者が承認した修正申請が全て表示されている
     */
    public function testApprovedTabDisplaysAllOwnApprovedRequests(): void
    {
        /**
         *1. 勤怠情報が登録されたユーザーにログインをする
         *2. 勤怠詳細を修正し保存処理をする
         *3. 申請一覧画面を開く
         *4. 管理者が承認した修正申請が全て表示されていることを確認
         * result. 承認済みに管理者が承認した申請が全て表示されている
         */
        $applications = $this->submitRequestsForUser($this->user);
        $otherApplications = $this->submitRequestsForUser(User::factory()->create());

        // 実際の承認処理を経由し、自分と他ユーザーの申請を2件ずつ承認する
        $this->actingAs($this->admin);
        foreach (array_merge(array_slice($applications, 0, 2), array_slice($otherApplications, 0, 2)) as $application) {
            $this->post("/stamp_correction_request/approve/{$application->id}")
                ->assertSessionHasNoErrors()
                ->assertRedirect("/stamp_correction_request/approve/{$application->id}");
            $this->assertDatabaseHas('applications', [
                'id' => $application->id,
                'approval_status' => '承認済み',
            ]);
        }

        $this->actingAs($this->user);
        $response = $this->get('/stamp_correction_request/list');
        $response->assertOk()->assertViewIs('user.user-application-list');
        $this->assertTabContainsRequests($response->getContent(), 'content2', array_slice($applications, 0, 2));
        $this->assertTabContainsRequests($response->getContent(), 'content1', [$applications[2]]);
        foreach ($otherApplications as $application) {
            $response->assertDontSee($application->comment);
        }
    }

    /**
     * ID11 各申請の「詳細」を押下すると勤怠詳細画面に遷移する
     */
    public function testEachRequestDetailLinkOpensItsAttendance(): void
    {
        /**
         * 1. 勤怠情報が登録されたユーザーにログインをする
         * 2. 勤怠詳細を修正し保存処理をする
         * 3. 申請一覧画面を開く
         * 4. 「詳細」ボタンを押す
         * result. 勤怠詳細画面に遷移する
         */
        $applications = $this->submitRequestsForUser($this->user);
        $response = $this->get('/stamp_correction_request/list');
        $response->assertOk()->assertViewIs('user.user-application-list');
        $this->assertTabContainsRequests($response->getContent(), 'content1', $applications);

        foreach ($applications as $application) {
            $this->get("/application/{$application->id}")
                ->assertRedirect("/attendance/{$application->attendance_id}");

            $attendance = $application->attendance;
            $this->get("/attendance/{$application->attendance_id}")
                ->assertOk()
                ->assertViewIs('user.user-detail')
                ->assertViewHas('data', fn($data) => (int) $data['id'] === $application->attendance_id)
                ->assertSee($attendance->comment);
        }

    }

    /** 修正前と異なる時刻で申請し、作成された申請を返す。 */
    private function submitCorrectionRequest(Attendance $attendance, string $comment): Application
    {
        $this->post("/attendance/{$attendance->id}", [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:30'],
            'new_break_out' => ['13:30'],
            'comment' => $comment,
        ])->assertSessionHasNoErrors()->assertRedirect("/attendance/{$attendance->id}");

        $this->assertSame(1, $attendance->applications()->count());

        return $attendance->applications()->firstOrFail();
    }

    /**
     * 別日の勤怠に対して3件申請し、「全て表示」を検証できるようにする。
     *
     * @return Application[]
     */
    private function submitRequestsForUser(User $user): array
    {
        $this->actingAs($user);
        $applications = [];
        for ($day = 2; $day <= 4; $day++) {
            $date = Carbon::parse('2026-09-01')->day($day);
            $attendance = $user->attendances()->create([
                'date' => $date,
                'clock_in' => $date->copy()->hour(9),
                'clock_out' => $date->copy()->hour(18),
                'comment' => 'テスト'
            ]);
            $applications[] = $this->submitCorrectionRequest($attendance, "ユーザー{$user->id}の{$day}日分の修正");
        }

        return $applications;
    }

    /** ページ全体ではなく、指定タブの申請行・詳細リンクを検証する。 */
    private function assertTabContainsRequests(string $html, string $tabId, array $applications, bool $admin = false): void
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $tabs = $xpath->query("//div[@id='$tabId']");
        $this->assertSame(1, $tabs->length);
        $links = $xpath->query('.//table//tr/td/a', $tabs->item(0));
        $actualUrls = [];
        foreach ($links as $link) {
            $actualUrls[] = $link->getAttribute('href');
        }
        $expectedUrls = [];
        foreach ($applications as $application) {
            $path = $admin ? '/stamp_correction_request/approve/' : '/application/';
            $expectedUrls[] = url($path . $application->id);
            $this->assertStringContainsString($application->comment, $tabs->item(0)->textContent);
        }
        $this->assertEqualsCanonicalizing($expectedUrls, $actualUrls);
    }
}
