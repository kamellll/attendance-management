<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
class StatusesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $now = now();

        $userIds = DB::table('users')->pluck('id');

        // 前月・前前月（今が2026-01なら 2025-12 と 2025-11）
        $months = [
            Carbon::now()->subMonthNoOverflow()->startOfMonth(),
            Carbon::now()->subMonthsNoOverflow(2)->startOfMonth(),
        ];

        foreach ($months as $monthStart) {
            $monthEnd = $monthStart->copy()->endOfMonth();

            for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
                // 平日のみ
                if ($date->isWeekend())
                    continue;

                $go = $date->copy()->setTime(9, 0, 0);
                $back = $date->copy()->setTime(18, 0, 0);

                // 休憩1時間、勤務8時間（秒）
                $restSeconds = 3600;
                $workSeconds = 8 * 3600; // 28800

                foreach ($userIds as $userId) {
                    DB::table('statuses')->updateOrInsert(
                        // 1日1件（出勤9:00固定なので go をキーにする）
                        ['user_id' => $userId, 'go' => $go],
                        [
                            'user_id' => $userId,
                            'status' => 3,          // 退勤済
                            'go' => $go,         // 出勤時刻
                            'back' => $back,       // 退勤時刻

                            // ★ 秒で統一
                            'rest' => $restSeconds, // 休憩合計(秒)
                            'sum' => $workSeconds, // 勤務合計(秒)

                            'apply' => 0,
                            'applied_at' => null,
                            'note' => null,

                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        }
    }
}
