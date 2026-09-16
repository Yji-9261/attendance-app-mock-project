<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Carbon\Carbon;

class AdminApprovalTest extends TestCase
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
     * ID15 承認待ちの修正申請が全て表示されている
     * @return void
     */
    public function test_can_view_all_pending_correction_requests(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 修正申請一覧ページを開き、承認待ちのタブを開く
         */
        $this->assertCorrectionRequestsInTab('content1', '承認待ち');
    }

    /**
     * ID15 承認済みの修正申請が全て表示されている
     * @return void
     */
    public function test_can_view_all_approved_correction_requests(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 修正申請一覧ページを開き、承認済みのタブを開く
         */

        $this->assertCorrectionRequestsInTab('content2', '承認済み');
    }


    /**
     * ID15 修正申請の詳細内容が正しく表示されている
     * @return void
     */
    public function test_can_view_correction_requests_dateil(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 修正申請の詳細画面を開く 
         */

        // 承認待ちのデータを作成
        $users = $this->createTestDatas();

        // 修正申請データをひとつ取得
        $application = $users->firstOrFail()
            ->applications()->firstOrFail();

        // 修正申請詳細画面表示 テスト
        $url = "stamp_correction_request/approve/{$application->id}";
        $response = $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertViewIs('admin.admin-application-detail');

        // 遷移先が選択した日の勤怠であることを、表示上で確認する
        $expected = [
            $application->attendance->user->name,
            $application->attendance->date->year . "年",
            $application->attendance->date->format('n月j日'),
            $application->new_clock_in->format('G:i'),
            $application->new_clock_out->format('G:i'),
        ];

        // 可変長休憩時間を取得
        foreach ($application->breakapplications as $breaktime) {
            $expected[] = $breaktime->break_in->format('G:i');
            $expected[] = $breaktime->break_out->format('G:i');
        }
        // 備考欄
        $expected[] = $application->comment;

        // 表示テスト
        $response->assertSeeInOrder($expected);

        // 修正ボタンが表示されているかテスト
        $response->assertSee('<button class="applied-form__button--submit" type="submit">承認</button>', false);
    }


    /**
     * ID15 修正申請の承認処理が正しく行われる
     * @return void
     */
    public function test_admin_can_approve_correction_request(): void
    {
        /**
         * 1. 管理者ユーザーにログインをする
         * 2. 修正申請の詳細画面で「承認」ボタンを押す
         */
        $users = $this->createTestDatas();
        $attendance = $users->firstOrFail()->attendances()->firstOrFail();
        $application = $attendance->applications()->firstOrFail();

        $expectedBreaks = $application->breakapplications()->orderBy('id')->get()
            ->map(fn($breaktime) => [
                $breaktime->break_in->toDateTimeString(),
                $breaktime->break_out->toDateTimeString(),
            ])->all();
        $this->assertSame('承認待ち', $application->approval_status);

        // 管理者で申請詳細を開き、承認ボタンから送信する
        $url = "/stamp_correction_request/approve/{$application->id}";
        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertViewIs('admin.admin-application-detail')
            ->assertSee('<button class="applied-form__button--submit" type="submit">承認</button>', false);

        $this->from($url)->post($url)
            ->assertSessionHasNoErrors()
            ->assertRedirect($url);

        // 申請が承認済みになり、対象勤怠に修正後の出退勤時刻が保存される
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'approval_status' => '承認済み',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'date' => $attendance->date->toDateTimeString(),
            'clock_in' => $application->new_clock_in->toDateTimeString(),
            'clock_out' => $application->new_clock_out->toDateTimeString(),
        ]);

        // 元の休憩が残らず、申請した休憩すべてに置き換わっている
        $actualBreaks = $attendance->breaktimes()->orderBy('id')->get()
            ->map(fn($breaktime) => [
                $breaktime->break_in->toDateTimeString(),
                $breaktime->break_out->toDateTimeString(),
            ])->all();
        $this->assertCount(2, $actualBreaks);
        $this->assertSame($expectedBreaks, $actualBreaks);

        // 承認後の詳細画面では「承認済み」と表示される
        $this->get($url)
            ->assertOk()
            ->assertViewIs('admin.admin-application-detail')
            ->assertSee('承認済み')
            ->assertDontSee('<button class="applied-form__button--submit" type="submit">承認</button>', false);
    }




    /** 指定タブに対象状態の申請だけが全件表示されることを確認する。 */
    private function assertCorrectionRequestsInTab(string $tabId, string $approvalStatus): void
    {
        $users = $this->createTestDatas($approvalStatus);

        // 反対の状態の申請も作成し、対象タブに混ざらないことを確認する
        $otherStatus = $approvalStatus === '承認待ち' ? '承認済み' : '承認待ち';
        $this->createTestDatas($otherStatus);

        $response = $this->actingAs($this->admin)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertViewIs('admin.admin-application-list');

        // 対象タブのHTMLを取得するためDOMを使用
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $tabs = $xpath->query("//div[@id='$tabId']");
        $this->assertSame(1, $tabs->length);

        // 対象申請の件数とタブ内の行数を比較し、余分な申請の混入も検出する
        $expectedCount = $users->sum(fn($user) => $user->applications->count());
        $this->assertSame(10, $expectedCount);
        $rows = $xpath->query('.//table//tr[td]', $tabs->item(0));
        $this->assertSame($expectedCount, $rows->length);

        foreach ($users as $user) {
            foreach ($user->applications as $application) {
                $detailUrl = url('/stamp_correction_request/approve/' . $application->id);
                $matchingRows = $xpath->query(".//tr[td/a[@href='$detailUrl']]", $tabs->item(0));
                $this->assertSame(1, $matchingRows->length);

                $cells = $xpath->query('./td', $matchingRows->item(0));
                $actual = [];
                foreach ($cells as $cell) {
                    $text = trim($cell->textContent);
                    $actual[] = $text;
                }
                $this->assertSame([
                    $approvalStatus,
                    $application->attendance->user->name,
                    $application->attendance->date->format('Y/m/d'),
                    $application->comment,
                    $application->application_date->format('Y/m/d'),
                    '詳細',
                ], $actual);
            }
        }
    }

    /**
     * Summary of createTestDatas
     * @return User|\Illuminate\Database\Eloquent\Collection<int, User|\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model
     */
    private function createTestDatas(string $approvalStatus = '承認待ち')
    {
        $users = User::factory()->count(10)->create();
        foreach ($users as $user) {
            $attendance = $user->attendances()->create([
                'date' => Carbon::now(),
                'clock_in' => Carbon::now()->hour(10),
                'clock_out' => Carbon::now()->hour(18),
            ]);
            $attendance->breaktimes()->create([
                'break_in' => Carbon::now()->hour(12),
                'break_out' => Carbon::now()->hour(13)
            ]);

            // 修正申請データ作成
            $appliation = $attendance->applications()->create([
                'application_date' => Carbon::now(),
                'new_clock_in' => Carbon::now()->hour(9),
                'new_clock_out' => Carbon::now()->hour(22),
                'comment' => '出退勤時間、休憩時間修正',
                'approval_status' => $approvalStatus,
            ]);

            // 休憩時間修正申請データ作成
            $appliation->breakapplications()->createMany([
                [
                    'break_in' => Carbon::now()->hour(11),
                    'break_out' => Carbon::now()->hour(12)
                ],
                [
                    'break_in' => Carbon::now()->hour(18),
                    'break_out' => Carbon::now()->hour(19)
                ]
            ]);
        }

        return $users;
    }
}
