<?php

namespace Tests\Feature;

use App\Http\Controllers\AttendanceController;
use App\Models\User;

use Database\Seeders\AttendanceSeeder;
use Database\Seeders\UserSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

use Tests\TestCase;

use Carbon\Carbon;

class ReportTest extends TestCase
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
     * マイ勤怠レポート機能	ゲストはレポートページにアクセスできない	
     * 1. 未認証で GET /attendance/report を実行
     * result. /login にリダイレクトされる
     * @return void
     */
    public function test_guest_cannot_access_report_page(): void
    {
        // 非認証ユーザーがアクセスした時に
        // loginページにリダイレクトされるテスト
        $this->get('/attendance/report')
            ->assertRedirect('login');
    }

    /**
     * 1. 認証ユーザーで勤怠データを複数日作成
     * 2. GET /attendance/report を実行
     * result.レスポンスに 
     *  summary（総労働時間/残業時間/平均値）
     *  monthly_trend
     *  anomalies 
     * が正しい値で含まれる
     * @return void
     */
    public function test_authenticated_user_gets_correct_report(): void
    {
        // CSVの対象期間に合わせて現在日時を固定する。
        $this->travelTo(Carbon::parse('2027-02-15 12:00:00'));

        // ユーザー作成時、現在日付を固定してテストするためメール認証日時を固定した日時として認証する
        $user = User::factory()->create(['email_verified_at' => now()]);

        // 6ヶ月期間外の8月分も登録し、レポートから除外されることを確認する。
        $this->createAttendancesFromCsv($user);

        // 結果期待値データをCSVから読み取り生成する
        [
            $expectedSummary,
            $expectedMonthlyTrend,
            $expectedAnomalies
        ]
            = $this->createExpectedReportFromCsv();

        $response = $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertViewIs('reports.index');

        // リポート結果を検証
        $this->checkRepotValues(
            $response,
            $expectedSummary,
            $expectedMonthlyTrend,
            $expectedAnomalies
        );
    }

    /**
     * 勤怠記録がないユーザーで安全に処理される	
     * 1. 勤怠データのないユーザーで認証
     * 2. GET /attendance/report を実行
     * result.各統計が 0 / 空配列で返り、エラーが発生しない
     */
    public function test_get_empty_attendance_records_safely()
    {
        $this->travelTo(Carbon::parse('2026-09-15 12:00:00'));
        $user = User::factory()->create(['email_verified_at' => now()]);

        // 空データアクセスして問題なくアクセスできるかテスト
        $response = $this->actingAs($user)
            ->get('/attendance/report')
            ->assertViewIs('reports.index')
            ->assertOk();

        // 結果期待値データを生成する
        [
            $expectedSummary,
            $expectedMonthlyTrend,
            $expectedAnomalies
        ] = $this->createEmptyExpectedData();

        // テスト
        $this->checkRepotValues(
            $response,
            $expectedSummary,
            $expectedMonthlyTrend,
            $expectedAnomalies
        );
    }

    private function checkRepotValues(
        $response,
        $expectedSummary,
        $expectedMonthlyTrend,
        $expectedAnomalies
    ) {
        $summary = $response->viewData('summary');
        $anomalies = $response->viewData('anomalies');
        $monthlyTrend = $response->viewData('monthlyTrend');

        // summaryチェック
        $this->assertEquals($expectedSummary, $summary);

        // anomaliesチェック
        $this->assertEquals($expectedAnomalies, $anomalies);

        // 件数と順番を含めてmonthlyTrendを確認する。
        $this->assertCount(count($expectedMonthlyTrend), $monthlyTrend);
        foreach ($monthlyTrend as $index => $row) {
            $this->assertEquals($expectedMonthlyTrend[$index], $row);
        }
    }

    /** BOM付きCSVを、ヘッダーをキーとする配列として読み込む。 */
    private function readReportCsv(string $filename): array
    {
        $path = base_path('tests/Fixtures/report/' . $filename);
        $handle = fopen($path, 'r');
        $this->assertNotFalse($handle, "CSVを開けません: {$filename}");

        try {
            $header = fgetcsv($handle);
            $this->assertIsArray($header, "CSVヘッダーがありません: {$filename}");
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle)) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                $this->assertCount(count($header), $values, "{$filename}: {$line}行目の列数が不正です");
                $rows[] = array_combine($header, $values);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** CSVの時刻に勤怠日を付けて、勤怠と複数休憩を登録する。 */
    private function createAttendancesFromCsv(User $user): void
    {
        $rows = $this->readReportCsv('attendances.csv');
        $this->assertNotEmpty($rows);
        $keys = array_column($rows, 'record_key');
        $this->assertCount(count($rows), array_unique($keys), 'record_keyが重複しています');

        foreach ($rows as $row) {
            $toDateTime = fn(string $time) => $time === ''
                ? null
                : Carbon::parse($row['date'] . ' ' . $time);

            $attendance = $user->attendances()->create([
                'date' => $row['date'],
                'clock_in' => $toDateTime($row['clock_in']),
                'clock_out' => $toDateTime($row['clock_out']),
            ]);

            foreach ($row as $column => $breakIn) {
                if (!preg_match('/^break_in_(.+)$/', $column, $matches)) {
                    continue;
                }
                $breakOutColumn = 'break_out_' . $matches[1];
                $this->assertArrayHasKey($breakOutColumn, $row);
                $breakOut = $row[$breakOutColumn];
                if ($breakIn === '' && $breakOut === '') {
                    continue;
                }
                $this->assertNotSame('', $breakIn, "勤怠{$row['record_key']}の休憩開始がありません");
                $attendance->breaktimes()->create([
                    'break_in' => $toDateTime($breakIn),
                    'break_out' => $toDateTime($breakOut),
                ]);
            }
        }
    }

    /** 入力勤怠から再計算せず、独立した期待値CSVを使用する。 */
    private function createExpectedReportFromCsv(): array
    {
        $rows = $this->readReportCsv('expected_report.csv');
        $this->assertCount(6, $rows);
        $totalWork = 0;
        $totalOvertime = 0;
        $totalDays = 0;
        $monthlyTrend = [];
        $anomalies = null;

        foreach ($rows as $row) {
            $totalWork += (int) $row['work_minutes'];
            $totalOvertime += (int) $row['overtime_minutes'];
            $totalDays += (int) $row['total_day'];
            $monthlyTrend[] = [
                'month' => (int) substr($row['month'], 5, 2),
                'work_minutes' => (int) $row['work_minutes'],
                'overtime_minutes' => (int) $row['overtime_minutes'],
            ];
            if ($row['month'] === now()->format('Y-m')) {
                $anomalies = [
                    'late_count' => (int) $row['late_count'],
                    'early_leave_count' => (int) $row['early_leave_count'],
                    'long_work_count' => (int) $row['long_work_count'],
                ];
            }
        }
        $this->assertNotNull($anomalies, '期待値CSVに基準月がありません');

        return [
            [
                'total_work_minutes' => $totalWork,
                'total_overtime_minutes' => $totalOvertime,
                'avg_work_minutes' => $totalDays > 0 ? $totalWork / $totalDays : 0,
            ],
            $monthlyTrend,
            $anomalies,
        ];
    }

    /**
     * 空データ時の期待値を返す
     * @return array<array|array{"avg_work_minutes": int, "total_overtime_minutes": int, "total_work_minutes": int|array{"early_leave_count": int, "late_count": int, "long_work_count": int}>}
     */
    private function createEmptyExpectedData(): array
    {
        // 基本情報
        $expectedSummary = [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ];

        // 異常検知
        $expectedAnomalies = [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ];

        // 月次推移
        $expectedMonthlyTrend = [
            [
                'month' => 9,
                'work_minutes' => 0,
                'overtime_minutes' => 0
            ],
            [
                'month' => 8,
                'work_minutes' => 0,
                'overtime_minutes' => 0
            ],
            [
                'month' => 7,
                'work_minutes' => 0,
                'overtime_minutes' => 0
            ],
            [
                'month' => 6,
                'work_minutes' => 0,
                'overtime_minutes' => 0
            ],
            [
                'month' => 5,
                'work_minutes' => 0,
                'overtime_minutes' => 0
            ],
            [
                'month' => 4,
                'work_minutes' => 0,
                'overtime_minutes' => 0
            ],
        ];
        return [
            $expectedSummary,
            $expectedMonthlyTrend,
            $expectedAnomalies,
        ];
    }
}
