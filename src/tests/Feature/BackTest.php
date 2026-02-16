<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Status;
use App\Models\Rest;
use Carbon\Carbon;
class BackTest extends TestCase
{
    use RefreshDatabase;
    public function test_back()
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
            'email_verified_at' => $base->toDateTimeString()
        ]);

        // 出勤済みの statuses（status=1 が「出勤中」想定）
        $status = Status::create([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $base->copy(),     // 2026-02-16 09:00:00
            'rest' => 3600,
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

        Rest::create([
            'statuses_id' => $status->id,
            'start' => $base->copy()->startOfDay()->setTime(12, 0, 0),
            'end' => $base->copy()->startOfDay()->setTime(13, 0, 0),
        ]);

        // DB確認：rests の start/end が 12:00〜13:00 になっているか
        $status->refresh();

        $this->assertDatabaseHas('rests', [
            'statuses_id' => $status->id,
            'start' => '2026-02-16 12:00:00',
            'end' => '2026-02-16 13:00:00',
        ]);

        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'rest' => 3600,
        ]);
        Carbon::setTestNow(Carbon::create(2026, 2, 16, 18, 0, 0, 'Asia/Tokyo'));
        $response = $this->followingRedirects()->post('/attendance/back');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }

    public function test_back_confirm()
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
            'email_verified_at' => $base->toDateTimeString()
        ]);

        // 出勤済みの statuses（status=1 が「出勤中」想定）
        $status = Status::create([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $base->copy(),     // 2026-02-16 09:00:00
            'rest' => 3600,
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

        Rest::create([
            'statuses_id' => $status->id,
            'start' => $base->copy()->startOfDay()->setTime(12, 0, 0),
            'end' => $base->copy()->startOfDay()->setTime(13, 0, 0),
        ]);

        // DB確認：rests の start/end が 12:00〜13:00 になっているか
        $status->refresh();

        $this->assertDatabaseHas('rests', [
            'statuses_id' => $status->id,
            'start' => '2026-02-16 12:00:00',
            'end' => '2026-02-16 13:00:00',
        ]);

        $this->assertDatabaseHas('statuses', [
            'id' => $status->id,
            'rest' => 3600,
        ]);
        Carbon::setTestNow(Carbon::create(2026, 2, 16, 18, 0, 0, 'Asia/Tokyo'));
        $response = $this->followingRedirects()->post('/attendance/back');

        $attendance = $this->get('/attendance/list');

        $attendance->assertStatus(200);
        $attendance->assertSee('18:00');
    }
}
