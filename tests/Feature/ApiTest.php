<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * seederからユーザー・勤怠データ作成
     */
    private function createRecordsFromSeed(): void
    {
        // シーディングを利用してユーザー・勤怠データ作成
        $this->seed(UserSeeder::class);
        $this->seed(AttendanceSeeder::class);
    }

    /**
     * 公開API 読み取り系	GET /api/v1/attendance-records で勤怠一覧が JSON で取得できる
     */
    public function testApiGetAttendanceRecordsIndex(): void
    {
        /**
         * 1.       シーディングで勤怠データを作成
         * 2.       GET /api/v1/attendance-records を実行
         * result.  HTTP 200 が返り、レスポンスに data 配列と meta 情報（current_page, last_page, per_page, total）が含まれる
         */
        $this->createRecordsFromSeed();

        // index apiアクセス検証
        // ステータスコード200が返り
        // レスポンスのjson構造を検証
        $this->getJson('api/v1/attendance-records')
            ->assertOk()
            ->assertJsonStructure(
                [
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'user_name',
                            'date',
                            'clock_in',
                            'clock_out',
                            'total_time',
                            'total_break_time',
                            'comment',
                        ],
                    ],
                    'links' => [
                    ],
                    'meta' => [
                        'current_page',
                        'from',
                        'last_page',
                        'per_page',
                        'to',
                        'total',
                    ],
                ]
            );
    }

    /**
     * GET /api/v1/attendance-records/{attendanceRecord} で勤怠詳細が JSON で取得できる
     */
    public function testApiGetAttendanceRecordsShow(): void
    {
        /**
         * 1.       勤怠データを作成
         * 2.       GET /api/v1/attendance-records/{id} を実行"
         * result.  HTTP 200 が返り、data に該当勤怠の詳細（**ユーザー・**休憩・修正申請含む）が含まれる
         */

        // 勤怠レコードのみの検証
        $onlyAttendance = $this->createOnlyAttendanceRecord();
        $this->testOnlyAttendanceRecord($onlyAttendance);

        // 各種レコード生成時の初期値データ兼、検証用データ
        $initialData = [
            'user_id' => 2,
            'user_name' => 'testuser2',
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '20:00:00',
            'break_in_01' => '12:00:00',
            'break_out_01' => '13:00:00',
            'break_in_02' => '18:00:00',
            'break_out_02' => '18:30:00',
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '22:00:00',
            'new_break_in_01' => '12:30:00',
            'new_break_out_01' => '13:30:00',
            'new_break_in_02' => '19:00:00',
            'new_break_out_02' => '19:30:00',
            'comment' => '',
            'application_date' => '2026-01-02',
        ];

        // 勤怠・休憩・勤怠修正申請・休憩修正申請レコードある時の検証
        $allInAttendance = $this->createAllInAttendanceRecord($initialData);
        $this->testAllInAttendanceRecord($allInAttendance, $initialData);
    }

    /**
     * 存在しない ID では 404 とエラー JSON が返る
     */
    public function testApiFailNonAttendanceRecordsShow(): void
    {
        /**
         * 1.       GET /api/v1/attendance-records/99999 を実行
         * result.  HTTP 404 が返り、レスポンスが { "error": "勤怠情報が見つかりませんでした。" }
         */
        $this->createRecordsFromSeed();
        $this->getJson('/api/v1/attendance-records/99999')
            ->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    /**
     * 公開API 書き込み系	POST /api/v1/attendance-records で勤怠が作成される
     */
    public function testApiCanCreateAttendanceRecords(): void
    {
        /**
         * 1.       正常なデータで POST /api/v1/attendance-records を実行
         * result.  HTTP 201 が返り、attendances テーブルにレコードが作成される
         */

        // ユーザー生成し、sanctum認証
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // 適当なリクエストを作成
        $date = Carbon::now()->startOfDay();
        $clock_in = $date->copy()->hour(9);
        $clock_out = $date->copy()->hour(18);

        // apiはY-m-d H:i:s形式で渡す
        // 201が返るか検証
        $this->postJson('/api/v1/attendance-records', [
            'date' => $date->toDateString(),
            'clock_in' => $clock_in->format('H:i:s'),
            'clock_out' => $clock_out->format('H:i:s'),
        ])->assertStatus(201);

        // テスト時に使用するsqliteはdate,time型がないのでdatetime型で検証
        $this->assertDatabaseHas('attendances', [
            'id' => 1,
            'date' => $date,
            'clock_in' => $clock_in,
            'clock_out' => $clock_out,
        ]);
    }

    /**
     * バリデーションエラー時に 422 と日本語エラーメッセージが返る
     */
    public function testApiValidationErrorMessageStore(): void
    {
        /**
         * 1.       不正なデータ（必須項目欠落）で POST /api/v1/attendance-records を実行
         * result.  HTTP 422 が返り、errors フィールドに日本語のエラーメッセージが含まれる
         */
        $faker = fake('ja_JP');

        // ユーザー生成し、sanctum認証
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // バリデーション違反の内容でpost実行
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '',
            'clock_in' => '',
            'clock_out' => '09:00',
            'comment' => $faker->realText(256),
        ])->assertStatus(422);

        // 期待通りのバリデーションか検証
        $response->assertJson([
            'message' => '勤怠日は必須です。 (and 3 more errors)',
            'errors' => [
                'date' => ['勤怠日は必須です。'],
                'clock_in' => ['出勤時刻は必須です。'],
                'clock_out' => ['退勤時刻は HH:MM:SS 形式で指定してください。'],
                'comment' => ['備考は 255 文字以内で入力してください。'],
            ],
        ]);
    }

    /**
     * PUT /api/v1/attendance-records/{attendanceRecord} で勤怠が更新される
     */
    public function testApiCanUpdateAttendanceRecords(): void
    {
        /**
         * 1.       既存勤怠に対して PUT で更新データを送信
         * 2.       存在しない ID に対して PUT を実行"
         * result.  HTTP 200 が返り、レコードが更新されている
         *          存在しない ID に対しては HTTP 404 を返す
         */

        // ユーザー生成し、sanctum認証
        $user = User::factory()->create();
        $attendance = $user->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        Sanctum::actingAs($user, ['*']);

        // date,clock_in,clock_outの値を更新してputリクエスト
        $updatedDate = $attendance->date->copy()->addDay();
        $updatedClockIn = $attendance->clock_in->copy()->addMinutes();
        $updatedClockOut = $attendance->clock_out->copy()->addMinutes();
        $this->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'date' => $updatedDate->format('Y-m-d'),
            'clock_in' => $updatedClockIn->format('H:i:s'),
            'clock_out' => $updatedClockOut->format('H:i:s'),
        ])->assertStatus(200);

        // テスト時に使用するsqliteはdate,time型がないのでdatetime型で検証
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'date' => $updatedDate,
            'clock_in' => $updatedClockIn,
            'clock_out' => $updatedClockOut,
        ]);

        // 存在しないIDに対してputした場合、404が返るかテスト
        $this->putJson('/api/v1/attendance-records/99999', [
            'date' => $updatedDate->format('Y-m-d'),
            'clock_in' => $updatedClockIn->format('H:i:s'),
            'clock_out' => $updatedClockOut->format('H:i:s'),
        ])->assertStatus(404);
    }

    /**
     * DELETE /api/v1/attendance-records/{attendanceRecord} で勤怠が削除される
     *
     * @return void
     */
    public function testApiCanDeleteAttendanceRecords()
    {
        /**
         * 1.       既存勤怠に対して DELETE を送信
         * 2.       存在しない ID に対して DELETE を実行
         * result.  HTTP 204 が返り、レコードが削除されている
         * 存在しない ID に対しては HTTP 404 を返す
         */

        // ユーザー生成し、sanctum認証
        $user = User::factory()->create();
        $attendance = $user->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        Sanctum::actingAs($user, ['*']);

        // データベースに登録されているかテスト
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
        ]);

        // deleteリクエストを行い、204が返ることをテスト
        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertStatus(204);

        // データベースから対象のレコードがなくなっていることをテスト
        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);

        // 存在しないIDにdeleteリクエストを行い、404が返ることをテスト
        $this->deleteJson('/api/v1/attendance-records/99999')
            ->assertStatus(404);
    }

    /**
     * Sanctum 認証	未認証時に書き込み系 API で 401 が返る
     */
    public function testApiSanctumUnautorizationStore(): void
    {
        /**
         * 1.       認証なしで POST/PUT/DELETE /api/v1/attendance-records を実行
         * result.  HTTP 401 が返り、レスポンスが { "message": "Unauthenticated." }
         */
        // ユーザーと勤怠生成
        $user = User::factory()->create();
        $attendance = $user->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);


        $date = Carbon::now()->startOfDay();
        $clock_in = $date->copy()->hour(9);
        $clock_out = $date->copy()->hour(18);

        // 勤怠401ステータス検証
        $this->postJson('/api/v1/attendance-records', [
            'date' => $date->toDateString(),
            'clock_in' => $clock_in->format('H:i:s'),
            'clock_out' => $clock_out->format('H:i:s'),
        ])->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        // 勤怠更新401ステータス検証
        $this->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'date' => $date->toDateString(),
            'clock_in' => $clock_in->format('H:i:s'),
            'clock_out' => $clock_out->format('H:i:s'),
        ])->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        // 勤怠削除401ステータス検証
        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

    }
    /**
     *  認証済みユーザーは自分の勤怠を更新・削除できる
     * 1.       Sanctum::actingAs($user) で認証
     * 2.       自分の勤怠に対して PUT/DELETE を実行
     * result.  HTTP 200/204 が返り、操作が成功する
     * testApiCanUpdateAttendanceRecords
     * testApiCanDeleteAttendanceRecords
     * にてテスト完了
     */

    /**
     * 他ユーザーの勤怠を更新・削除しようとすると 403 が返る
     *
     * @return void
     */
    public function testApiAnotherUserForbittenUpdateDestroy()
    {
        /**
         * 1.       Sanctum::actingAs($user) で認証
         * 2.       他ユーザーの勤怠に対して PUT/DELETE を実行
         * result.  HTTP 403 が返り、レスポンスが { "error": "この操作を実行する権限がありません。" }
         */

        // 操作される側のユーザーと対象の勤怠レコードを生成
        $anotherUser = User::factory()->create();
        $attendance = $anotherUser->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 操作ユーザー生成し、sanctum認証
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // 認証ユーザーでない勤怠データに対し
        // put実行し、403とエラーメッセージが返るか検証
        $updatedDate = $attendance->date->copy()->addDay();
        $updatedClockIn = $attendance->clock_in->copy()->addMinutes();
        $updatedClockOut = $attendance->clock_out->copy()->addMinutes();
        $this->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'date' => $updatedDate->format('Y-m-d'),
            'clock_in' => $updatedClockIn->format('H:i:s'),
            'clock_out' => $updatedClockOut->format('H:i:s'),
        ])->assertStatus(403)
            ->assertJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        // delete実行し、403とエラーメッセージが返るかテスト
        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertStatus(403)
            ->assertJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);
    }

    /**
     * テスト用に勤怠レコードのみ生成
     *
     * @return Model
     */
    private function createOnlyAttendanceRecord(): Attendance
    {
        $user = User::factory()->create([
            'name' => 'testuser1',
            'email' => 'testuser1@example.com',
            'password' => 'passeord',
        ]);

        // 勤怠データ生成
        // 休憩・勤怠修正申請レコードはなし
        $attendance = $user->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '',
        ]);

        return $attendance;
    }

    /**
     * 勤怠レコードのみの詳細レスポンス検証
     *
     * @param  mixed  $attendance  勤怠レコード
     */
    private function testOnlyAttendanceRecord($attendance): void
    {
        $this->get("/api/v1/attendance-records/{$attendance->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $attendance->id,
                    'user' => [
                        'id' => $attendance->user->id,
                        'name' => $attendance->user->name,
                    ],
                    'date' => '2026-01-01',
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                    'comment' => '',
                    'breaks' => [],
                    'applications' => [],
                ],
            ]);
    }

    /**
     * 勤怠・休憩・勤怠修正申請・休憩修正申請レコード生成
     *
     * @param  array  $initialData  初期値データ
     * @return Model
     */
    private function createAllInAttendanceRecord(array $initialData): Attendance
    {
        $user = User::factory()->create([
            'id' => $initialData['user_id'],
            'name' => $initialData['user_name'],
            'email' => 'testuser2@example.com',
            'password' => 'password',
        ]);

        $attendance = $user->attendances()->create([
            'date' => $initialData['date'],
            'clock_in' => $initialData['clock_in'],
            'clock_out' => $initialData['clock_out'],
        ]);

        $attendance->breaktimes()->createMany([
            [
                'break_in' => $initialData['break_in_01'],
                'break_out' => $initialData['break_out_01'],
            ],
            [
                'break_in' => $initialData['break_in_02'],
                'break_out' => $initialData['break_out_02'],
            ],
        ]);

        $application = $attendance->applications()->create([
            'application_date' => $initialData['application_date'],
            'new_clock_in' => $initialData['new_clock_in'],
            'new_clock_out' => $initialData['new_clock_out'],
            'comment' => $initialData['comment'],
        ]);

        $application->breakapplications()->createMany([
            [
                'break_in' => $initialData['new_break_in_01'],
                'break_out' => $initialData['new_break_out_01'],
            ],
            [
                'break_in' => $initialData['new_break_in_02'],
                'break_out' => $initialData['new_break_out_02'],
            ],
        ]);

        return $attendance;
    }

    /**
     * 勤怠・休憩・勤怠修正申請・休憩修正申請レコード存在時の詳細レスポンス検証
     *
     * @param  Attendance  $attendance  勤怠レコード
     * @param  array  $expectedData  レスポンス期待値データ
     */
    private function testAllInAttendanceRecord(Attendance $attendance, array $expectedData): void
    {
        $response = $this
            ->get("/api/v1/attendance-records/{$attendance->id}")
            ->assertOk();

        $response->assertJson([
            'data' => [
                'id' => $attendance->id,
                'user' => [
                    'id' => $expectedData['user_id'],
                    'name' => $expectedData['user_name'],
                ],
                'date' => $expectedData['date'],
                'clock_in' => $expectedData['clock_in'],
                'clock_out' => $expectedData['clock_out'],
                'comment' => $expectedData['comment'],
                'breaks' => [
                    [
                        'id' => 1,
                        'attendance_id' => $attendance->id,
                        'break_in' => $expectedData['break_in_01'],
                        'break_out' => $expectedData['break_out_01'],
                    ],
                    [
                        'id' => 2,
                        'attendance_id' => $attendance->id,
                        'break_in' => $expectedData['break_in_02'],
                        'break_out' => $expectedData['break_out_02'],
                    ],
                ],
                'applications' => [
                    [
                        'id' => 1,
                        'attendance_id' => $attendance->id,
                        'application_date' => $expectedData['application_date'],
                        'new_clock_in' => $expectedData['new_clock_in'],
                        'new_clock_out' => $expectedData['new_clock_out'],
                        'comment' => $expectedData['comment'],
                        'break_applications' => [
                            [
                                'id' => 1,
                                'application_id' => 1,
                                'break_in' => $expectedData['new_break_in_01'],
                                'break_out' => $expectedData['new_break_out_01'],
                            ],
                            [
                                'id' => 2,
                                'application_id' => 1,
                                'break_in' => $expectedData['new_break_in_02'],
                                'break_out' => $expectedData['new_break_out_02'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
