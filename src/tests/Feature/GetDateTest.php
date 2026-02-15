<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
class GetDateTest extends TestCase
{
    use RefreshDatabase;
    public function test_match_time()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 現在時刻を固定
        $now = Carbon::create(2026, 2, 15, 21, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // ユーザー作成＆ログイン
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
            'admin' => 0,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);

        // controllerと同じ加工をテスト側でも再現
        $jpWeek = ['日', '月', '火', '水', '木', '金', '土'];
        $week = $jpWeek[$now->dayOfWeek]; // 0=日, 6=土

        // 例: 2026年1月18日(日)
        $expectedDateWithWeek = $now->format('Y年n月j日') . "({$week})";

        // 時刻表示も検証（例: 21:00）
        $expectedTime = $now->format('H:i');

        $response->assertSee($expectedDateWithWeek);
        $response->assertSee($expectedTime);

        Carbon::setTestNow(); // 解除
    }
}
