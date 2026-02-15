<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Status;
use App\Models\Rest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class RestTest extends TestCase
{
    use RefreshDatabase;
    public function test_go_list()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 「今日」を固定（テストが日付に依存しないように）
        $now = Carbon::create(2026, 2, 15, 19, 0, 0, 'Asia/Tokyo'); // 19:00
        Carbon::setTestNow($now);

        // ユーザー作成＆ログイン
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
            'admin' => 0,
            'email_verified_at' => $now->toDateTimeString(),
        ]);

        // 今日の 09:00 を go にした statuses レコードを作成
        // 「勤務外」が status=3 の表示なら、status=3 をセット
        Status::create([
            'user_id' => $user->id,
            'status' => 1, // ←あなたの仕様に合わせて変更（勤務外のコード）
            'go' => $now->copy()->startOfDay()->setTime(9, 0, 0),
            'apply' => 0,
            'applied_at' => null,
            'note' => null,
        ]);
        $login = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $login->assertStatus(302);
        // attendance 画面を表示して「勤務外」が見えることを確認
        //$response = $this->actingAs($user)->get('/attendance');
        $response = $this->followingRedirects()->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertSee('休憩入');

        $response = $this->followingRedirects()->post('/attendance/rest');

        $response->assertStatus(200);
        $response->assertSee('休憩中');

        Carbon::setTestNow(); // 解除
    }
}
