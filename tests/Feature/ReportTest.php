<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private const REFERENCE_DATETIME = '2027-02-15 12:00:00';

    // 本番コードとは独立して、仕様の対象月数を定義する。
    private const EXPECTED_REPORT_MONTHS = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // レポート検証用CSVの対象期間に合わせて現在日時を固定する。
        $this->travelTo(Carbon::parse(self::REFERENCE_DATETIME));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * マイ勤怠レポート機能	ゲストはレポートページにアクセスできない
     */
    public function testGuestCannotAccessReportPage(): void
    {
        // 1. 未認証で GET /attendance/report を実行
        // result. /login にリダイレクトされる

        // 非認証ユーザーがアクセスした時に
        // loginページにリダイレクトされるテスト
        $this->get('/attendance/report')
            ->assertRedirect('login');
    }

    /**
     * 認証ユーザーの統計情報が正しく計算される
     */
    public function testAuthenticatedUserGetsCorrectReport(): void
    {
        // 1. 認証ユーザーで勤怠データを複数日作成
        // 2. GET /attendance/report を実行
        // result.レスポンスに
        //  summary（総労働時間/残業時間/平均値）
        //  monthly_trend
        //  anomalies
        // が正しい値で含まれる

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

        // 勤怠レポート画面遷移検証
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
     */
    public function testGetEmptyAttendanceRecordsSafely(): void
    {
        // 1. 勤怠データのないユーザーで認証
        // 2. GET /attendance/report を実行
        // result.各統計が 0 / 空配列で返り、エラーが発生しない

        $user = User::factory()->create(['email_verified_at' => now()]);

        // 空データの勤怠状況で問題なくアクセスできるかテスト
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

    /**
     * レポートのビューに渡された集計値・月次推移・異常検知件数を期待値と比較する。
     *
     * @param  TestResponse  $response  レポートページのレスポンス
     * @param  array<string, int|float>  $expectedSummary  総労働時間・残業時間・平均労働時間の期待値（分）
     * @param  list<array{month: string, work_minutes: int, overtime_minutes: int}>  $expectedMonthlyTrend  表示順に並べた月別の期待値
     * @param  array<string, int>  $expectedAnomalies  当月の遅刻・早退・長時間労働の件数の期待値
     */
    private function checkRepotValues(
        TestResponse $response,
        array $expectedSummary,
        array $expectedMonthlyTrend,
        array $expectedAnomalies
    ): void {
        $summary = $response->viewData('summary');
        $anomalies = $response->viewData('anomalies');
        $monthlyTrend = $response->viewData('monthlyTrend');

        // 全期間の総労働時間・残業時間・平均労働時間を検証する。
        $this->assertEquals($expectedSummary, $summary);

        // 当月の遅刻・早退・長時間労働の件数を検証する。
        $this->assertEquals($expectedAnomalies, $anomalies);

        // 月次推移の件数・順序・各月の集計値を検証する。
        $this->assertCount(count($expectedMonthlyTrend), $monthlyTrend);
        foreach ($monthlyTrend as $index => $row) {
            $this->assertEquals($expectedMonthlyTrend[$index], $row);
        }
    }

    /**
     * BOM付きCSVを、ヘッダーをキーとする配列として読み込む
     *
     * @param  string  $filename  検証用のデータを作成するためのCSVファイルパス
     * @return array[]
     */
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

    /**
     * CSVの時刻に勤怠日を付けて、勤怠と複数休憩を登録する。
     *
     * @param  User  $user  ユーザーモデル
     */
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

    /**
     * 入力勤怠から再計算せず、独立した期待値CSVを使用する
     *
     * @return array<array|array{"avg_work_minutes": float|int, "total_overtime_minutes": int, "total_work_minutes": int|array{"early_leave_count": int, "late_count": int, "long_work_count": int}|mixed>}
     */
    private function createExpectedReportFromCsv(): array
    {
        $rows = $this->readReportCsv('expected_report.csv');
        $this->assertCount(self::EXPECTED_REPORT_MONTHS, $rows);
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
                'month' => $row['month'],
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
     *
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

        // 固定した現在日時を基準に、当月から過去6ヶ月分を降順で生成する。
        $baseMonth = now()->startOfMonth();
        $expectedMonthlyTrend = [];
        for ($offset = 0; $offset < self::EXPECTED_REPORT_MONTHS; $offset++) {
            $expectedMonthlyTrend[] = [
                'month' => $baseMonth->copy()->subMonthsNoOverflow($offset)->format('Y-m'),
                'work_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        return [
            $expectedSummary,
            $expectedMonthlyTrend,
            $expectedAnomalies,
        ];
    }
}
