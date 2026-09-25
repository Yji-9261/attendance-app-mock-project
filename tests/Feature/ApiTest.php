<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Models\User;
use App\Models\Attendance;
use Database\Seeders\UserSeeder;
use Database\Seeders\AttendanceSeeder;

use Laravel\Sanctum\Sanctum;

use Carbon\Carbon;

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
     * seedからユーザー・勤怠データ作成
     * @return void
     */
    private function createRecordsFromSeed()
    {
        // シーディングを利用してユーザー・勤怠データ作成
        $this->seed(UserSeeder::class);
        $this->seed(AttendanceSeeder::class);
    }

    /**
     * 公開API 読み取り系	GET /api/v1/attendance-records で勤怠一覧が JSON で取得できる	
     * 1. シーディングで勤怠データを作成
     * 2. GET /api/v1/attendance-records を実行
     * result.HTTP 200 が返り、レスポンスに data 配列と meta 情報（current_page, last_page, per_page, total）が含まれる
     */
    public function test_api_get_attendance_records_index()
    {
        $this->createRecordsFromSeed();

        // index apiアクセステスト
        $response = $this->getJson('api/v1/attendance-records')
            ->assertOk();

        // 出力構造テスト
        $response->assertJsonStructure(
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
                        'comment'
                    ],
                ],
                'links' => [
                ],
                'meta' => [
                    "current_page",
                    "from",
                    "last_page",
                    "per_page",
                    "to",
                    "total",
                ]
            ]
        );
    }
    /** 
     * GET /api/v1/attendance-records/{attendanceRecord} で勤怠詳細が JSON で取得できる	
     * 1. 勤怠データを作成
     * 2. GET /api/v1/attendance-records/{id} を実行"
     * result.HTTP 200 が返り、data に該当勤怠の詳細（**ユーザー・**休憩・修正申請含む）が含まれる
     */
    public function test_api_get_attendance_records_show()
    {
        $this->createAndTestEmptyData();
        $this->createAndTestExistsData();
    }

    /**
     * 存在しない ID では 404 とエラー JSON が返る	
     * 1. GET /api/v1/attendance-records/99999 を実行
     * result.HTTP 404 が返り、レスポンスが { "error": "勤怠情報が見つかりませんでした。" }
     */
    public function test_api_fail_non_attendance_records_show()
    {
        $this->createRecordsFromSeed();
        $response = $this->getJson("/api/v1/attendance-records/99999")
            ->assertStatus(404)
            ->assertJson([
                'error' => '勤怠情報が見つかりませんでした。'
            ]);
    }

    /**
     * 公開API 書き込み系	POST /api/v1/attendance-records で勤怠が作成される	
     * 1. 正常なデータで POST /api/v1/attendance-records を実行	
     * result.HTTP 201 が返り、attendance_records テーブルにレコードが作成される
     * @return void
     */
    public function test_api_can_create_attendance_records()
    {
        // sanctum認証
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $date = Carbon::now()->startOfDay();
        $clock_in = $date->copy()->hour(9);
        $clock_out = $date->copy()->hour(18);

        // apiはY-m-d H:i:s形式で渡す
        $this->postJson('/api/v1/attendance-records', [
            'date' => $date->toDateString(),
            'clock_in' => $clock_in->format('H:i:s'),
            'clock_out' => $clock_out->format('H:i:s'),
        ])->assertStatus(201);

        // modelでdatetimeでcastしているためdateime形式でテスト
        $this->assertDatabaseHas('attendances', [
            'id' => 1,
            'date' => $date,
            'clock_in' => $clock_in,
            'clock_out' => $clock_out,
        ]);
    }
    /* 
     * バリデーションエラー時に 422 と日本語エラーメッセージが返る	
     * 1. 不正なデータ（必須項目欠落）で POST /api/v1/attendance-records を実行	
     * result.HTTP 422 が返り、errors フィールドに日本語のエラーメッセージが含まれる
     */
    public function test_api_validation_error_message_store()
    {
        $faker = fake('ja_JP');

        // sanctum認証
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '',
            'clock_in' => '',
            'clock_out' => '09:00',
            'comment' => $faker->realText(256),
        ])->assertStatus(422);

        $response->assertJson([
            "message" => '勤怠日は必須です。 (and 3 more errors)',
            "errors" => [
                'date' => ['勤怠日は必須です。'],
                'clock_in' => ['出勤時刻は必須です。'],
                'clock_out' => ['退勤時刻は HH:MM:SS 形式で指定してください。'],
                'comment' => ['備考は 255 文字以内で入力してください。']
            ]
        ]);
    }

    /* 
     * PUT /api/v1/attendance-records/{attendanceRecord} で勤怠が更新される	
     * 1. 既存勤怠に対して PUT で更新データを送信
     * 2. 存在しない ID に対して PUT を実行"	
     * result.HTTP 200 が返り、レコードが更新されている 
     * 存在しない ID に対しては HTTP 404 を返す
     */
    public function test_api_can_update_attendance_records()
    {
        // sanctum認証
        $user = User::factory()->create();
        $attendance = $user->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        Sanctum::actingAs($user, ['*']);

        // date,clock_in,clock_outを更新
        $updatedDate = $attendance->date->copy()->addDay();
        $updatedClockIn = $attendance->clock_in->copy()->addMinutes();
        $updatedClockOut = $attendance->clock_out->copy()->addMinutes();

        $this->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'date' => $updatedDate->format('Y-m-d'),
            'clock_in' => $updatedClockIn->format('H:i:s'),
            'clock_out' => $updatedClockOut->format('H:i:s'),
        ])->assertStatus(200);

        // modelでdatetimeでcastしているためdateime形式でテスト
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'date' => $updatedDate,
            'clock_in' => $updatedClockIn,
            'clock_out' => $updatedClockOut,
        ]);

        // 存在しないIDに対してputした場合に404が返るかテスト
        $this->putJson("/api/v1/attendance-records/99999", [
            'date' => $updatedDate->format('Y-m-d'),
            'clock_in' => $updatedClockIn->format('H:i:s'),
            'clock_out' => $updatedClockOut->format('H:i:s'),
        ])->assertStatus(404);
    }
    /**
     * DELETE /api/v1/attendance-records/{attendanceRecord} で勤怠が削除される	
     * 1. 既存勤怠に対して DELETE を送信
     * 2. 存在しない ID に対して DELETE を実行
     * result.HTTP 204 が返り、レコードが削除されている 
     * 存在しない ID に対しては HTTP 404 を返す
     */
    public function test_api_can_delete_attendance_records()
    {
        $user = User::factory()->create();
        $attendance = $user->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        Sanctum::actingAs($user, ['*']);

        // データベースに登録をテスト
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id
        ]);

        // deleteリクエストと204が返ることをテスト
        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertStatus(204);

        // データベースから対象のidがなくなっていることをテスト
        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id
        ]);

        // 存在しないIDにdeleteリクエスト、404が返ることをテスト
        $this->deleteJson("/api/v1/attendance-records/99999")
            ->assertStatus(404);
    }

    /**
     * Sanctum 認証	未認証時に書き込み系 API で 401 が返る	
     * 1. 認証なしで POST/PUT/DELETE /api/v1/attendance-records を実行	
     * HTTP 401 が返り、レスポンスが { "message": "Unauthenticated." }   
     */
    public function test_api_sanctum_unautorization_store()
    {
        $date = Carbon::now()->startOfDay();
        $clock_in = $date->copy()->hour(9);
        $clock_out = $date->copy()->hour(18);

        // apiはY-m-d H:i:s形式で渡す
        $this->postJson('/api/v1/attendance-records', [
            'date' => $date->toDateString(),
            'clock_in' => $clock_in->format('H:i:s'),
            'clock_out' => $clock_out->format('H:i:s'),
        ])->assertStatus(401)
            ->assertJson([
                "message" => "Unauthenticated."
            ]);
    }
    /**
     *  認証済みユーザーは自分の勤怠を更新・削除できる	
     * 1. Sanctum::actingAs($user) で認証
     * 2. 自分の勤怠に対して PUT/DELETE を実行
     * HTTP 200/204 が返り、操作が成功する
     * test_api_can_update_attendance_records
     * test_api_can_delete_attendance_records
     * にてテスト完了
     */

    /* 
     * 他ユーザーの勤怠を更新・削除しようとすると 403 が返る
     * 1. Sanctum::actingAs($user) で認証
     * 2. 他ユーザーの勤怠に対して PUT/DELETE を実行	
     * HTTP 403 が返り、レスポンスが { "error": "この操作を実行する権限がありません。" }
     */
    public function test_api_another_user_forbitten_update_destroy()
    {
        // ユーザーと勤怠データを作成
        $anotherUser = User::factory()->create();
        $attendance = $anotherUser->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 操作ユーザー作成
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // date,clock_in,clock_outを更新
        $updatedDate = $attendance->date->copy()->addDay();
        $updatedClockIn = $attendance->clock_in->copy()->addMinutes();
        $updatedClockOut = $attendance->clock_out->copy()->addMinutes();

        // put実行し403とエラーメッセージが返るかテスト
        $this->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'date' => $updatedDate->format('Y-m-d'),
            'clock_in' => $updatedClockIn->format('H:i:s'),
            'clock_out' => $updatedClockOut->format('H:i:s'),
        ])->assertStatus(403)
            ->assertJson([
                "error" => "この操作を実行する権限がありません。"
            ]);

        // delete実行し403とエラーメッセージが返るかテスト
        $this->deleteJson("/api/v1/attendance-records/{$attendance->id}")
            ->assertStatus(403)
            ->assertJson([
                "error" => "この操作を実行する権限がありません。"
            ]);
    }

    /**
     * breaktimes,applications,breakapplicationsなしテスト  
     */
    private function createAndTestEmptyData()
    {
        // ユーザー1
        $user = User::factory()->create([
            'name' => 'testuser1',
            'email' => 'testuser1@example.com',
            'password' => 'passeord',
        ]);
        // 勤怠データ1(休憩・申請なし)
        $attendance = $user->attendances()->create([
            'date' => '2026-01-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->get("/api/v1/attendance-records/{$attendance->id}")
            ->assertOk();

        $response->assertJson([
            "data" => [
                "id" => $attendance->id,
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name
                ],
                "date" => '2026-01-01',
                "clock_in" => '09:00:00',
                "clock_out" => '18:00:00',
                "comment" => '',
                "breaks" => [],
                "applications" => [],
            ]
        ]);
    }

    /**
     * breaktimes,applications,breakapplications生成しテスト  
     */
    private function createAndTestExistsData()
    {
        $date = '2026-01-01';
        $clock_in = '09:00:00';
        $clock_out = '20:00:00';
        $break_in_01 = '12:00:00';
        $break_out_01 = '13:00:00';
        $break_in_02 = '18:00:00';
        $break_out_02 = '18:30:00';
        $new_clock_in = '10:00:00';
        $new_clock_out = '22:00:00';
        $new_break_in_01 = '12:30:00';
        $new_break_out_01 = '13:30:00';
        $new_break_in_02 = '19:00:00';
        $new_break_out_02 = '19:30:00';
        $comment = '勤怠修正';
        $userName = 'testuser2';

        $user = User::factory()->create([
            'name' => $userName,
            'email' => 'testuser2@example.com',
            'password' => 'password',
        ]);

        // 勤怠データ2(休憩・申請あり)
        $attendance = $user->attendances()->create([
            'date' => $date,
            'clock_in' => $clock_in,
            'clock_out' => $clock_out,
        ]);

        $attendance->breaktimes()->createMany([
            [
                'break_in' => $break_in_01,
                'break_out' => $break_out_01,
            ],
            [
                'break_in' => $break_in_02,
                'break_out' => $break_out_02,
            ],
        ]);

        $attendance->applications()->create([
            'application_date' => $date,
            'new_clock_in' => $new_clock_in,
            'new_clock_out' => $new_clock_out,
            'comment' => $comment
        ])->breakapplications()->createMany([
                    [
                        'break_in' => $new_break_in_01,
                        'break_out' => $new_break_out_01,
                    ],
                    [
                        'break_in' => $new_break_in_02,
                        'break_out' => $new_break_out_02,
                    ]
                ]);

        $response = $this->get("/api/v1/attendance-records/{$attendance->id}")
            ->assertOk();

        $response->assertJson([
            "data" => [
                "id" => $attendance->id,
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                ],
                "date" => $date,
                "clock_in" => $clock_in,
                "clock_out" => $clock_out,
                "comment" => $comment,
                "breaks" => [
                    [
                        "id" => 1,
                        "attendance_id" => $attendance->id,
                        "break_in" => $break_in_01,
                        "break_out" => $break_out_01,
                    ],
                    [
                        "id" => 2,
                        "attendance_id" => $attendance->id,
                        "break_in" => $break_in_02,
                        "break_out" => $break_out_02,
                    ],
                ],
                "applications" => [
                    [
                        "id" => 1,
                        'attendance_id' => $attendance->id,
                        'application_date' => $date,
                        'new_clock_in' => $new_clock_in,
                        'new_clock_out' => $new_clock_out,
                        'comment' => $comment,
                        'break_applications' => [
                            [
                                "id" => 1,
                                'application_id' => 1,
                                'break_in' => $new_break_in_01,
                                'break_out' => $new_break_out_01,
                            ],
                            [
                                "id" => 2,
                                'application_id' => 1,
                                'break_in' => $new_break_in_02,
                                'break_out' => $new_break_out_02,
                            ],
                        ]
                    ]
                ],
            ]
        ]);
    }
}
