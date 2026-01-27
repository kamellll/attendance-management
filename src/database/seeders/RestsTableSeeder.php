<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
class RestsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $now = now();

        // 前前月の月初〜前月の月末（statuses を拾って rests を作る）
        $start = Carbon::now()->subMonthsNoOverflow(2)->startOfMonth()->startOfDay();
        $end = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay();

        $statuses = DB::table('statuses')
            ->select('id', 'go')
            ->whereBetween('go', [$start, $end])
            ->get();

        foreach ($statuses as $s) {
            $go = Carbon::parse($s->go);

            // 休憩 12:00-13:00
            $startRest = $go->copy()->setTime(12, 0, 0);
            $endRest = $go->copy()->setTime(13, 0, 0);

            DB::table('rests')->updateOrInsert(
                [
                    'statuses_id' => $s->id,
                    'start' => $startRest,
                ],
                [
                    'statuses_id' => $s->id,
                    'start' => $startRest,
                    'end' => $endRest,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
