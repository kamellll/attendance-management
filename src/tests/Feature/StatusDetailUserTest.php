<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StatusDetailUserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function name_field_on_detail_page_matches_logged_in_user()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 日付固定（任意）
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) 勤怠が登録されたユーザーを作成
        $plainPassword = 'Password123!';
        $userName = 'ログイン太郎';

        $user = User::create([
            'name' => $userName,
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);

        // email_verified_at が create() で入らない対策（確実に認証済みにする）
        $user->forceFill([
            'email_verified_at' => $now->toDateTimeString(),
        ])->save();

        // 1) statuses を1件作っておく（詳細ページ用）
        $go = Carbon::create(2026, 2, 16, 9, 0, 0, 'Asia/Tokyo');
        $back = Carbon::create(2026, 2, 16, 18, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 3,
            'go' => $go->toDateTimeString(),
            'rest' => 0,
            'back' => $back->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 2) 勤怠情報が登録されたユーザーにログインをする（コントローラー経由）
        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => $plainPassword,
        ]);

        $login->assertStatus(302);
        $login->assertRedirect('/attendance');
        $this->assertAuthenticatedAs($user);

        // 3) 勤怠詳細ページを開く
        $detail = $this->get("/attendance/detail/{$statusId}");
        $detail->assertStatus(200);

        // 4) 名前欄を確認する（ログインユーザー名が表示されているか）
        $detail->assertSee($userName);

        Carbon::setTestNow(); // 解除
    }

    /** @test */
    public function detail_page_shows_selected_date_in_date_field()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト基準の現在時刻（任意）
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) ユーザー作成
        $plainPassword = 'Password123!';
        $user = User::create([
            'name' => 'ログイン太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);

        // email_verified_at が create() で入らない対策
        $user->forceFill([
            'email_verified_at' => $now->toDateTimeString(),
        ])->save();

        // 1) 「選択した日付」の勤怠を作成（例：2026-02-15）
        $selectedGo = Carbon::create(2026, 2, 15, 9, 0, 0, 'Asia/Tokyo');
        $selectedBack = Carbon::create(2026, 2, 15, 18, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 3,
            'go' => $selectedGo->toDateTimeString(),
            'rest' => 0,
            'back' => $selectedBack->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 2) ログイン（コントローラー経由）
        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => $plainPassword,
        ]);

        $login->assertStatus(302);
        $login->assertRedirect('/attendance');
        $this->assertAuthenticatedAs($user);

        // 3) 勤怠詳細ページを開く
        $detail = $this->get("/attendance/detail/{$statusId}");
        $detail->assertStatus(200);

        // 4) 日付欄が「選択した日付」になっているか確認（2/15(日) 形式）
        $expectedDate = $selectedGo->format('n月j日');

        $detail->assertSee($expectedDate);

        Carbon::setTestNow(); // 解除
    }

    /** @test */
    public function detail_page_shows_go_and_back_time_matching_registered_values()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト基準の現在時刻（任意）
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) 勤怠が登録されたユーザーを作成（認証済みにする）
        $plainPassword = 'Password123!';
        $user = User::create([
            'name' => 'ログイン太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);

        // email_verified_at が create() で入らない対策
        $user->forceFill([
            'email_verified_at' => $now->toDateTimeString(),
        ])->save();

        // 1) 選択した日付の勤怠を作成（例：2026-02-15）
        $selectedGo = Carbon::create(2026, 2, 15, 9, 0, 0, 'Asia/Tokyo');
        $selectedBack = Carbon::create(2026, 2, 15, 18, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 3,
            'go' => $selectedGo->toDateTimeString(),
            'rest' => 0,
            'back' => $selectedBack->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 2) ログイン（コントローラー経由）
        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => $plainPassword,
        ]);

        $login->assertStatus(302);
        $login->assertRedirect('/attendance');
        $this->assertAuthenticatedAs($user);

        // 3) 勤怠詳細ページを開く
        $detail = $this->get("/attendance/detail/{$statusId}");
        $detail->assertStatus(200);

        // 4) 出勤・退勤欄の時刻が登録されたものと一致しているか確認（HH:MM）
        $expectedGo = $selectedGo->format('H:i');     // 09:00
        $expectedBack = $selectedBack->format('H:i'); // 18:00

        $detail->assertSee($expectedGo);
        $detail->assertSee($expectedBack);

        Carbon::setTestNow(); // 解除
    }

    /** @test */
    public function detail_page_shows_rest_time_correctly()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト基準時刻（任意）
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) ユーザー作成（認証済みにする）
        $plainPassword = 'Password123!';

        $user = User::create([
            'name' => 'ログイン太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);

        // email_verified_at が create() で入らないケース対策
        $user->forceFill([
            'email_verified_at' => $now->toDateTimeString(),
        ])->save();

        // 1) 勤怠（statuses）を作成（例：2026-02-15）
        $selectedDate = Carbon::create(2026, 2, 15, 9, 0, 0, 'Asia/Tokyo');
        $go = $selectedDate->copy()->setTime(9, 0, 0);
        $back = $selectedDate->copy()->setTime(18, 0, 0);

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 3,
            'go' => $go->toDateTimeString(),
            'rest' => 3600, // 休憩合計（秒）※表示に使ってないなら0でもOK
            'back' => $back->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 2) rests を作成（12:00〜13:00）
        $restStart = $selectedDate->copy()->setTime(12, 0, 0);
        $restEnd = $selectedDate->copy()->setTime(13, 0, 0);

        DB::table('rests')->insert([
            'statuses_id' => $statusId,
            'start' => $restStart->toDateTimeString(),
            'end' => $restEnd->toDateTimeString(),
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 3) ログイン（コントローラー経由）
        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => $plainPassword,
        ]);

        $login->assertStatus(302);
        $login->assertRedirect('/attendance');
        $this->assertAuthenticatedAs($user);

        // 4) 勤怠詳細ページを開く
        $detail = $this->get("/attendance/detail/{$statusId}");
        $detail->assertStatus(200);

        // 5) 休憩欄を確認（開始・終了が表示されているか）
        // 画面側が H:i 表示の想定
        $detail->assertSee('12:00');
        $detail->assertSee('13:00');

        Carbon::setTestNow(); // 解除
    }
}
