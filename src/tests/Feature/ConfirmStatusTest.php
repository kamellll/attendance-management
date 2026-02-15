<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Status;
use Carbon\Carbon;
use App\Models\Rest;

class ConfirmStatusTest extends TestCase
{
    use RefreshDatabase;
    public function test_status_0()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        $now = Carbon::create(2026, 2, 15, 19, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
            'admin' => 0,
            'email_verified_at' => $now->toDateTimeString(), // ★確実に入れる
        ]);

        $login = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $login->assertStatus(302);
        $response = $this->followingRedirects()->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertSee('勤務外');

        Carbon::setTestNow();
    }

    public function test_status_1()
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
        $response->assertSee('出勤中');

        Carbon::setTestNow(); // 解除
    }

    public function test_status_2()
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
        $status = Status::create([
            'user_id' => $user->id,
            'status' => 2, // ←あなたの仕様に合わせて変更（勤務外のコード）
            'go' => $now->copy()->startOfDay()->setTime(9, 0, 0),
            'apply' => 0,
            'applied_at' => null,
            'note' => null,
        ]);

        Rest::create([
            'statuses_id' => $status->id,
            'start' => $now->copy()->startOfDay()->setTime(12, 0, 0),
            'end' => null, // end は休憩戻りで入れる想定
        ]);

        $login = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $login->assertStatus(302);

        // attendance 画面を表示して「勤務外」が見えることを確認
        $response = $this->followingRedirects()->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertSee('休憩中');

        Carbon::setTestNow(); // 解除
    }

    public function test_status_3()
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
        $status = Status::create([
            'user_id' => $user->id,
            'status' => 3, // ←あなたの仕様に合わせて変更（勤務外のコード）
            'go' => $now->copy()->startOfDay()->setTime(9, 0, 0),
            'back' => $now->copy()->startOfDay()->setTime(18, 0, 0), // 退勤時刻（必要なら）
            'rest' => 3600,  // 休憩1時間（秒）
            'sum' => 8 * 3600, // 勤務8時間（秒）
            'apply' => 0,
            'applied_at' => null,
            'note' => null,
        ]);

        Rest::create([
            'statuses_id' => $status->id,
            'start' => $now->copy()->startOfDay()->setTime(12, 0, 0),
            'end' => $now->copy()->startOfDay()->setTime(13, 0, 0), // end は休憩戻りで入れる想定
        ]);

        $login = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $login->assertStatus(302);

        // attendance 画面を表示して「勤務外」が見えることを確認
        $response = $this->followingRedirects()->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertSee('退勤済');

        Carbon::setTestNow(); // 解除
    }
}
