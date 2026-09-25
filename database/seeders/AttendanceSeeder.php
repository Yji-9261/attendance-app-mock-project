<?php

namespace Database\Seeders;

use App\Models\User;

use Illuminate\Database\Seeder;

use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDummyForUser1();
        $this->createDummyForUser2();
    }

    /**
     * 検証用のダミー勤怠データ
     * 開発プロセス意図的データ含む
     * @return void
     */
    private function createDummyForUser1()
    {
        // 対象のユーザー取得
        $user = User::where('name', 'user1')->firstOrFail();

        // 基準データ
        $baseDate = Carbon::now()->startOfMonth();

        // 7ヶ月前のデータ生成
        $currentDate = $baseDate->copy()
            ->subMonthsNoOverflow(6)
            ->startOfMonth()
            ->hour(9);

        $endDate = $currentDate->copy()->endOfMonth()->startOfDay()->hour(9);

        // 平日は全て作成する
        while ($currentDate->timestamp < $endDate->timestamp) {
            //　通常勤怠作成
            $this->createDummy($user, $currentDate, 9, 0, 18, 0);
            $currentDate->addDay();
        }

        /**　
         * 以下、意図的データ作成 
         */

        // 過去6ヶ月分のデータ生成
        $currentDate = $baseDate->copy()
            ->subMonthsNoOverflow(5)
            ->startOfMonth()
            ->hour(9);

        //月毎の勤怠作成個数
        $createdConutMonthly = 0;

        // ベース日付まで勤怠を生成する
        while ($currentDate->timestamp < $baseDate->timestamp) {

            // 月毎の勤怠作成個数が15未満で、平日なら勤怠データを生成
            if ($createdConutMonthly < 15 && $currentDate->isWeekday()) {

                // ダミーデータ作成
                $this->createDummy($user, $currentDate, 9, 0, 18, 0);

                // 月毎の勤怠作成日をカウント
                $createdConutMonthly += 1;
            }

            // 月最終日ならクリア
            if ($currentDate->isLastOfMonth()) {
                $createdConutMonthly = 0;
            }

            // 日付を進める
            $currentDate->addDay();
        }

        /**　
         * 今月の勤怠レコード作成
         * 勤怠異常データ(遅刻/早退/長時間労働)含む
         */
        // 通常10件
        $this->createDummy($user, $currentDate, 9, 0, 18, 0, 10, true);
        // 残業時間3件
        $this->createDummy($user, $currentDate, 9, 0, 20, 0, 3, true);
        // 遅刻2件
        $this->createDummy($user, $currentDate, 9, 30, 18, 0, 2, true);
        // 早退1件
        $this->createDummy($user, $currentDate, 9, 0, 17, 0, 1, true);
        // 長時間労働1件
        $this->createDummy($user, $currentDate, 8, 0, 21, 0, 1, true);
    }

    private function createDummyForUser2()
    {
        // 基準データ
        $baseDate = Carbon::now()->startOfMonth();

        // 過去6ヶ月分のデータ生成
        $currentDate = $baseDate->copy()
            ->subMonthsNoOverflow(5)
            ->startOfMonth()
            ->hour(9);

        $user = User::where('name', 'user2')->firstOrFail();

        // 5月前勤怠生成(空データ)
        //$this->createDummy($user, $currentDate, 9, 0, 18, 0, 20, true);

        // 4月前勤怠生成(出勤時間遅めに)
        $currentDate = $baseDate->copy()
            ->subMonthsNoOverflow(4)
            ->startOfMonth()
            ->hour(9);
        $this->createDummy($user, $currentDate, 12, 0, 18, 0, 15, true);

        // 3月前勤怠生成(出勤時間早めに)
        $currentDate = $baseDate->copy()
            ->subMonthsNoOverflow(3)
            ->startOfMonth()
            ->hour(9);
        $this->createDummy($user, $currentDate, 9, 0, 17, 0, 15, true);

        // 2月前勤怠生成
        $currentDate = $baseDate->copy()
            ->subMonthsNoOverflow(2)
            ->startOfMonth()
            ->hour(9);
        $this->createDummy($user, $currentDate, 9, 0, 18, 0, 15, true);

        // 1月前勤怠生成
        $currentDate = $baseDate->copy()
            ->subMonthsNoOverflow(1)
            ->startOfMonth()
            ->hour(9);
        $this->createDummy($user, $currentDate, 9, 0, 18, 0, 15, true);

        // 今月勤怠生成
        $currentDate = $baseDate->copy()
            ->startOfMonth()
            ->hour(9);
        $this->createDummy($user, $currentDate, 9, 1, 18, 30, 5, true);
        $this->createDummy($user, $currentDate, 9, 0, 17, 59, 5, true);
        $this->createDummy($user, $currentDate, 9, 0, 23, 00, 5, true);
    }

    /**
     * Summary of createDummy
     * @param mixed $user
     * @param mixed $currentDate
     * @param mixed $startHours
     * @param mixed $startMinutes
     * @param mixed $endHours
     * @param mixed $endMinutes
     * @param mixed $num
     * @param mixed $isAddDate
     * @return void
     */
    private function createDummy(
        $user,
        $currentDate,
        $startHours,
        $startMinutes,
        $endHours,
        $endMinutes,
        $num = 1,
        $isAddDate = false,
        $createdWeekEnd = false,
    ) {
        //for ($i = 0; $i < $num; ++$i) {
        while ($num) {
            $date = $currentDate->copy();

            // 日付を次に進める
            if ($isAddDate) {
                $currentDate->addDay();
            }

            // 週末に勤怠作成しないモードなら作成しない
            if (!$createdWeekEnd && $date->isWeekEnd()) {
                if (!$isAddDate) {
                    // 次の日に進めないモードなら無限ループになるので終了させる    
                    break;
                }
                continue;
            }

            // 勤怠データ生成
            $user->attendances()->create([
                'date' => $date->copy()->hour($startHours)->minutes($startMinutes),
                'clock_in' => $date->copy()->hour($startHours)->minutes($startMinutes),
                'clock_out' => $date->copy()->hour($endHours)->minutes($endMinutes),
            ])->breaktimes()->create([
                        'break_in' => $date->copy()->hour(12),
                        'break_out' => $date->copy()->hour(13),
                    ]);

            // 生成回数減算
            $num -= 1;
        }
    }
}
