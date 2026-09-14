<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Application;
use App\Models\User;
use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Carbon\Carbon;

class CorrectRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証可能ユーザー
     * @var User
     */
    protected User $user;

    protected User $admin;

    protected Attendance $attendance;

    protected array $postData;


    protected function setUp(): void
    {
        parent::setUp();

        // 一般ユーザー登録
        $this->user = User::factory()->create();
        // 認証
        $this->actingAs($this->user);

        // 管理者ユーザー登録
        $this->admin = User::factory()->create(['admin_status' => true]);

        // 勤怠データ登録
        Carbon::setTestNow(Carbon::parse('2026-09-13 10:00:00'));
        $date = Carbon::parse('2026-09-01 09:00:00');

        $attendance = $this->user->attendances()->create([
            'date' => $date->day(1)->hour(9),
            'clock_in' => $date,
            'clock_out' => $date->copy()->hour(18),
        ]);
        $attendance->breaktimes()->create([
            'break_in' => $date->copy()->hour(12),
            'break_out' => $date->copy()->hour(13),
        ]);
        $this->attendance = $attendance;


        // ポストデータ
        $this->postData = [
            'new_clock_in' => $attendance->clock_in->format('H:i'),
            'new_clock_out' => $attendance->clock_out->format('H:i'),

            // new_break_in/new_break_outは配列形式で渡す
            'new_break_in' => $attendance->breaktimes->map(function ($record) {
                return $record->break_in->format('H:i');
            })->toArray(),
            'new_break_out' => $attendance->breaktimes->map(function ($record) {
                return $record->break_out->format('H:i');
            })->toArray(),

            'comment' => '備考',
        ];
    }

    protected function tearDown(): void
    {
        // 時刻固定の解除
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
     * @return void
     */
    public function test_expected_validation_message_for_clock_in_after_clock_out()
    {
        // 勤怠詳細アクセス確認
        $response = $this->get("/attendance/{$this->attendance->id}");
        $response->assertViewIs('user.user-detail');

        // ポストリクエストを変更
        // 出勤時間より早くする
        $this->postData['new_clock_in'] =
            $this->attendance
                ->clock_out
                ->copy()
                ->addHour()
                ->format('H:i');

        $response = $this->post("/attendance/{$this->attendance->id}", $this->postData);
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です'
        ]);
    }


    /**
     * 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
     * @return void
     */
    public function test_expected_validation_message_for_break_in_after_clock_out()
    {
        // ポストリクエストを変更
        // 出勤時間より早くする
        $this->postData['new_break_in'][0] =
            $this->attendance->
                clock_out
                ->copy()
                ->addHour()
                ->format('H:i');

        $response = $this->post("/attendance/{$this->attendance->id}", $this->postData);

        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です'
        ]);
    }

    /**
     * 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
     * @return void
     */
    public function test_expected_validation_message_for_break_out_after_clock_out()
    {
        // ポストリクエストを変更
        // 出勤時間より早くする
        $this->postData['new_break_out'][0] =
            $this->attendance->
                clock_out
                ->copy()
                ->addHour()
                ->format('H:i');

        $response = $this->post("/attendance/{$this->attendance->id}", $this->postData);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な時間です'
        ]);
    }

    /**
     * 備考欄が未入力の場合のエラーメッセージが表示される
     * @return void
     */
    public function test_expected_validation_message_for_comment_required()
    {
        // ポストリクエストを変更
        // 出勤時間より早くする
        $this->postData['comment'] = '';

        $response = $this->post("/attendance/{$this->attendance->id}", $this->postData);

        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください'
        ]);
    }

    /**
     * 修正申請処理が実行される
     * @return void
     */
    public function test_user_can_submit_correction_request_and_admin_can_view_it(): void
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

        $application = $this->submitCorrectionRequest($this->attendance, '打刻時刻を訂正します');
        $date = $this->attendance->date->toDateString();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'attendance_id' => $this->attendance->id,
            'new_clock_in' => "$date 09:30:00",
            'new_clock_out' => "$date 18:30:00",
            'comment' => '打刻時刻を訂正します',
            'approval_status' => '承認待ち',
            'application_date' => Carbon::now()->toDateTimeString(),
        ]);
        $this->assertSame(1, $application->breakapplications()->count());
        $this->assertDatabaseHas('break_applications', [
            'application_id' => $application->id,
            'break_in' => "$date 12:30:00",
            'break_out' => "$date 13:30:00",
        ]);

        // 承認前は元の勤怠・休憩が変更されない
        $this->assertDatabaseHas('attendances', [
            'id' => $this->attendance->id,
            'clock_in' => "$date 09:00:00",
            'clock_out' => "$date 18:00:00",
        ]);
        $this->assertSame(1, $this->attendance->breaktimes()->count());
        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $this->attendance->id,
            'break_in' => "$date 12:00:00",
            'break_out' => "$date 13:00:00",
        ]);

        // 3. 管理者ユーザーで承認画面と申請一覧画面を確認する 
        $this->actingAs($this->admin);
        $this->get("/stamp_correction_request/approve/{$application->id}")
            ->assertOk()
            ->assertViewIs('admin.admin-application-detail')
            ->assertSee($this->user->name)
            ->assertSee('打刻時刻を訂正します')
            ->assertSee('9:30')
            ->assertSee('18:30')
            ->assertSee('12:30')
            ->assertSee('13:30');

        $response = $this->get('/stamp_correction_request/list');
        $response->assertOk()->assertViewIs('admin.admin-application-list');
        $this->assertTabContainsRequests($response->getContent(), 'content1', [$application], true);

    }

    /**
     * ID11「承認待ち」にログインユーザーが行った申請が全て表示されていること
     * @return void
     */
    public function test_pending_tab_displays_all_own_correction_requests(): void
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
     * @return void
     */
    public function test_approved_tab_displays_all_own_approved_requests(): void
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
     * @return void
     */
    public function test_each_request_detail_link_opens_its_attendance(): void
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
            $this->get("/attendance/{$application->attendance_id}")
                ->assertOk()
                ->assertViewIs('user.user-detail')
                ->assertViewHas('data', fn($data) => (int) $data['id'] === $application->attendance_id)
                ->assertSee($application->comment);
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

    /** 別日の勤怠に対して3件申請し、「全て表示」を検証できるようにする。 */
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
            ]);
            $applications[] = $this->submitCorrectionRequest($attendance, "ユーザー{$user->id}の{$day}日分の修正");
        }

        return $applications;
    }

    /** ページ全体ではなく、指定タブの申請行・詳細リンクを検証する。 */
    private function assertTabContainsRequests(string $html, string $tabId, array $applications, bool $admin = false): void
    {
        $document = new \DOMDocument();
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
