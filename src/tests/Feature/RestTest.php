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
    public function test_rest_once()
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

    public function test_rest_second()
    {
        //休憩は1日に何回でもできる
        config(['app.timezone' => 'Asia/Tokyo']);

        // 「今日」を固定（テストが日付に依存しないように）
        $now = Carbon::create(2026, 2, 16, 19, 0, 0, 'Asia/Tokyo'); // 19:00
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

        //1回目の休憩
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response = $this->followingRedirects()->post('/attendance/rest');

        //休憩中の確認
        $response->assertStatus(200);
        $response->assertSee('休憩中');

        //休憩から戻り、再度「休憩入」が表示される確認
        $response = $this->followingRedirects()->post('/attendance/backRest');
        $response->assertStatus(200);
        $response->assertSee('休憩入');


        Carbon::setTestNow(); // 解除
    }

    public function test_rest_back()
    {
        //休憩は1日に何回でもできる
        config(['app.timezone' => 'Asia/Tokyo']);

        // 「今日」を固定（テストが日付に依存しないように）
        $now = Carbon::create(2026, 2, 16, 19, 0, 0, 'Asia/Tokyo'); // 19:00
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

        //1回目の休憩
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response = $this->followingRedirects()->post('/attendance/rest');

        //休憩中の確認
        $response->assertStatus(200);
        $response->assertSee('休憩中');

        //休憩から戻り、再度「休憩入」が表示される確認
        $response = $this->followingRedirects()->post('/attendance/backRest');
        $response->assertStatus(200);
        $response->assertSee('出勤中');


        Carbon::setTestNow(); // 解除
    }

    public function test_rest_back_second()
    {
        //休憩は1日に何回でもできる
        config(['app.timezone' => 'Asia/Tokyo']);

        // 「今日」を固定（テストが日付に依存しないように）
        $now = Carbon::create(2026, 2, 16, 19, 0, 0, 'Asia/Tokyo'); // 19:00
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

        //1回目の休憩
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response = $this->followingRedirects()->post('/attendance/rest');

        //休憩中の確認
        $response->assertStatus(200);
        $response->assertSee('休憩中');

        //休憩から戻る
        $response = $this->followingRedirects()->post('/attendance/backRest');
        $response->assertStatus(200);

        //2回目の休憩
        $response = $this->followingRedirects()->post('/attendance/rest');

        //休憩中の確認
        $response->assertStatus(200);
        $response->assertSee('休憩中');

        //休憩から戻り、再度「出勤中」が表示される確認
        $response = $this->followingRedirects()->post('/attendance/backRest');
        $response->assertStatus(200);
        $response->assertSee('出勤中');
        Carbon::setTestNow(); // 解除
    }

    public function test_list()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 9:00（出勤済み状態を作るための基準）
        $base = Carbon::create(2026, 2, 16, 9, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($base);

        // ユーザー作成（email_verified_at が create で入らないなら forceFill を使う）
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
            'admin' => 0,
        ]);
        $user->forceFill(['email_verified_at' => $base->toDateTimeString()])->save();

        // 出勤済みの statuses（status=1 が「出勤中」想定）
        $status = Status::create([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $base->copy(),     // 2026-02-16 09:00:00
            'rest' => 0,
            'back' => null,
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'note' => null,
        ]);

        // ログイン（コントローラー経由）
        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ])->assertRedirect('/attendance');

        $this->assertAuthenticatedAs($user);

        // 休憩入ボタンが見えること
        $res = $this->get('/attendance');
        $res->assertStatus(200);
        $res->assertSee('休憩入');

        // --- ここから「時刻を切り替える」 ---
        // 12:00 にして休憩入
        Carbon::setTestNow(Carbon::create(2026, 2, 16, 12, 0, 0, 'Asia/Tokyo'));
        $res = $this->followingRedirects()->post('/attendance/rest', []);
        $res->assertStatus(200);
        $res->assertSee('休憩中');

        // 13:00 にして休憩戻
        Carbon::setTestNow(Carbon::create(2026, 2, 16, 13, 0, 0, 'Asia/Tokyo'));
        $res = $this->followingRedirects()->post('/attendance/backRest', []);
        $res->assertStatus(200);
        $res->assertSee('出勤中');

        // DB確認：rests の start/end が 12:00〜13:00 になっているか
        $status->refresh();

        $this->assertDatabaseHas('rests', [
            'statuses_id' => $status->id,
            'start' => '2026-02-16 12:00:00',
            'end' => '2026-02-16 13:00:00',
        ]);

        // statuses の休憩秒が 3600 になっているか（仕様が「戻り時に加算」なら）
        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'rest' => 3600,
        ]);

        Carbon::setTestNow(); // 解除

        $attendance = $this->get('/attendance/list');
        $attendance->assertStatus(200);
        $attendance->assertSee('1:00');
    }

}
