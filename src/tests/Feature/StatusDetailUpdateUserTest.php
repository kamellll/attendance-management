<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StatusDetailUpdateUserTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function it_rejects_when_go_is_after_back_on_update()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト日付固定
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) ユーザー作成（認証済みにする）
        $plainPassword = 'Password123!';
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);
        // email_verified_at が create() で入らない環境対策
        $user->forceFill(['email_verified_at' => $now->toDateTimeString()])->save();

        // 1) statuses を作成（対象日：2026-02-16）
        $baseDate = Carbon::create(2026, 2, 16, 0, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $baseDate->copy()->setTime(9, 0, 0)->toDateTimeString(),
            'rest' => 0,
            'back' => $baseDate->copy()->setTime(18, 0, 0)->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 休憩1件（バリデーションが配列前提の場合の保険）
        $restId = DB::table('rests')->insertGetId([
            'statuses_id' => $statusId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
            'end' => $baseDate->copy()->setTime(13, 0, 0)->toDateTimeString(),
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

        // 4) 出勤を退勤より後にして保存（go=19:00, back=18:00）
        $payload = [
            'statuses_id' => $statusId,

            // ※コントローラーが userId を参照している実装の場合の保険
            'userId' => $user->id,

            'go' => '19:00',
            'back' => '18:00',

            // 既存休憩（配列）
            'rest_ids' => [$restId],
            'start' => ['12:00'],
            'end' => ['13:00'],

            // 追加休憩（空でOK）
            'new_start' => '',
            'new_end' => '',

            // note 必須対策（ここで落ちないように必ず入れる）
            'note' => 'テスト更新',
        ];

        $update = $this->post('/attendance/update', $payload);

        // バリデーション失敗は通常リダイレクト（302）で元の画面へ
        $update->assertStatus(302);

        // 「go >= back」のエラーが back に付く想定
        $update->assertSessionHasErrors(['back']);

        // メッセージまで確認したい
        $update->assertSessionHasErrors([
            'back' => '出勤時間が不適切な値です',
        ]);

        // 任意：DBが更新されていないことも確認（goは元の09:00のまま）
        $this->assertDatabaseHas('statuses', [
            'id' => $statusId,
            'go' => $baseDate->copy()->setTime(9, 0, 0)->toDateTimeString(),
        ]);

        Carbon::setTestNow(); // 解除
    }

    /** @test */
    public function it_rejects_when_rest_start_is_after_back_on_update()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト日付固定
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) ユーザー作成（認証済みにする）
        $plainPassword = 'Password123!';
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);
        // email_verified_at が create() で入らない環境対策
        $user->forceFill(['email_verified_at' => $now->toDateTimeString()])->save();

        // 1) statuses を作成（対象日：2026-02-16、退勤は18:00）
        $baseDate = Carbon::create(2026, 2, 16, 0, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $baseDate->copy()->setTime(9, 0, 0)->toDateTimeString(),
            'rest' => 0,
            'back' => $baseDate->copy()->setTime(18, 0, 0)->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 休憩1件（本来は12:00〜13:00）
        $restId = DB::table('rests')->insertGetId([
            'statuses_id' => $statusId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
            'end' => $baseDate->copy()->setTime(13, 0, 0)->toDateTimeString(),
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

        // 4) 休憩開始を退勤より後にして保存（back=18:00, start=18:30）
        $payload = [
            'statuses_id' => $statusId,

            // コントローラーが userId を参照している実装なら必要
            'userId' => $user->id,

            'go' => '09:00',
            'back' => '18:00',

            // 既存休憩（配列）
            'rest_ids' => [$restId],
            'start' => ['18:30'], // ★退勤より後（不正）
            'end' => ['18:45'],

            // 追加休憩（空でOK）
            'new_start' => '',
            'new_end' => '',

            // note 必須
            'note' => 'テスト更新',
        ];

        $update = $this->post('/attendance/update', $payload);

        // バリデーション失敗 → リダイレクト（302）
        $update->assertStatus(302);

        // エラーが start.* に付く想定（実装により start / start.0 / start[] など）
        // まずは start にエラーがあることだけ確認（メッセージまで見るなら下も）
        $update->assertSessionHasErrors(['start.0']);

        // メッセージまで固定で確認したい場合は、あなたの文言に合わせて変更してください
        // 例：
        $update->assertSessionHasErrors([
            'start.0' => '休憩時間が不適切な値です（1件目）',
        ]);

        // 任意：DBが更新されていないこと（休憩startが元の12:00のまま）
        $this->assertDatabaseHas('rests', [
            'id' => $restId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
        ]);

        Carbon::setTestNow(); // 解除
    }

    /** @test */
    public function it_shows_validation_message_when_rest_end_is_after_back()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト日付固定
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) ユーザー作成（認証済みにする）
        $plainPassword = 'Password123!';
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);
        // email_verified_at が create() で入らない環境対策
        $user->forceFill(['email_verified_at' => $now->toDateTimeString()])->save();

        // 1) statuses を作成（対象日：2026-02-16、退勤は18:00）
        $baseDate = Carbon::create(2026, 2, 16, 0, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $baseDate->copy()->setTime(9, 0, 0)->toDateTimeString(),
            'rest' => 0,
            'back' => $baseDate->copy()->setTime(18, 0, 0)->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 休憩1件（本来は12:00〜13:00）
        $restId = DB::table('rests')->insertGetId([
            'statuses_id' => $statusId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
            'end' => $baseDate->copy()->setTime(13, 0, 0)->toDateTimeString(),
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

        // 4) 休憩開始を退勤より後にして保存（back=18:00, start=18:30）
        $payload = [
            'statuses_id' => $statusId,

            // コントローラーが userId を参照している実装なら必要
            'userId' => $user->id,

            'go' => '09:00',
            'back' => '18:00',

            // 既存休憩（配列）
            'rest_ids' => [$restId],
            'start' => ['16:00'],
            'end' => ['18:45'], // ★退勤より後（不正）

            // 追加休憩（空でOK）
            'new_start' => '',
            'new_end' => '',

            // note 必須
            'note' => 'テスト更新',
        ];

        $update = $this->post('/attendance/update', $payload);

        // バリデーション失敗 → リダイレクト（302）
        $update->assertStatus(302);

        // エラーが start.* に付く想定（実装により start / start.0 / start[] など）
        // まずは start にエラーがあることだけ確認（メッセージまで見るなら下も）
        $update->assertSessionHasErrors(['end.0']);

        // メッセージまで固定で確認したい場合は、あなたの文言に合わせて変更してください
        // 例：
        $update->assertSessionHasErrors([
            'end.0' => '休憩時間もしくは退勤時間が不適切な値です（1件目）',
        ]);

        // 任意：DBが更新されていないこと（休憩startが元の12:00のまま）
        $this->assertDatabaseHas('rests', [
            'id' => $restId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
        ]);

        Carbon::setTestNow(); // 解除
    }

    /** @test */
    public function it_shows_validation_message_when_note_null()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト日付固定
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) ユーザー作成（認証済みにする）
        $plainPassword = 'Password123!';
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);
        // email_verified_at が create() で入らない環境対策
        $user->forceFill(['email_verified_at' => $now->toDateTimeString()])->save();

        // 1) statuses を作成（対象日：2026-02-16、退勤は18:00）
        $baseDate = Carbon::create(2026, 2, 16, 0, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $baseDate->copy()->setTime(9, 0, 0)->toDateTimeString(),
            'rest' => 0,
            'back' => $baseDate->copy()->setTime(18, 0, 0)->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 休憩1件（本来は12:00〜13:00）
        $restId = DB::table('rests')->insertGetId([
            'statuses_id' => $statusId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
            'end' => $baseDate->copy()->setTime(13, 0, 0)->toDateTimeString(),
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

        // 4) 休憩開始を退勤より後にして保存（back=18:00, start=18:30）
        $payload = [
            'statuses_id' => $statusId,

            // コントローラーが userId を参照している実装なら必要
            'userId' => $user->id,

            'go' => '09:00',
            'back' => '18:00',

            // 既存休憩（配列）
            'rest_ids' => [$restId],
            'start' => '12:00',
            'end' => '13:00', // ★退勤より後（不正）

            // 追加休憩（空でOK）
            'new_start' => '',
            'new_end' => '',

            // note 必須
            'note' => '',
        ];

        $update = $this->post('/attendance/update', $payload);

        // バリデーション失敗 → リダイレクト（302）
        $update->assertStatus(302);

        // エラーが start.* に付く想定（実装により start / start.0 / start[] など）
        // まずは start にエラーがあることだけ確認（メッセージまで見るなら下も）
        $update->assertSessionHasErrors(['note']);

        // メッセージまで固定で確認したい場合は、あなたの文言に合わせて変更してください
        // 例：
        $update->assertSessionHasErrors([
            'note' => '備考を記入してください',
        ]);

        // 任意：DBが更新されていないこと（休憩startが元の12:00のまま）
        $this->assertDatabaseHas('rests', [
            'id' => $restId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
        ]);

        Carbon::setTestNow(); // 解除
    }

    /** @test */
    public function update_detail()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // テスト日付固定
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 0) ユーザー作成（認証済みにする）
        $plainPassword = 'Password123!';
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);
        // email_verified_at が create() で入らない環境対策
        $user->forceFill(['email_verified_at' => $now->toDateTimeString()])->save();

        // 1) statuses を作成（対象日：2026-02-16、退勤は18:00）
        $baseDate = Carbon::create(2026, 2, 16, 0, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 1,
            'go' => $baseDate->copy()->setTime(9, 0, 0)->toDateTimeString(),
            'rest' => 0,
            'back' => $baseDate->copy()->setTime(18, 0, 0)->toDateTimeString(),
            'sum' => 0,
            'apply' => 0,
            'applied_at' => null,
            'rest_add' => 0,
            'note' => null,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        // 休憩1件（本来は12:00〜13:00）
        $restId = DB::table('rests')->insertGetId([
            'statuses_id' => $statusId,
            'start' => $baseDate->copy()->setTime(12, 0, 0)->toDateTimeString(),
            'end' => $baseDate->copy()->setTime(13, 0, 0)->toDateTimeString(),
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

        // 4) 休憩開始を退勤より後にして保存（back=18:00, start=18:30）
        $payload = [
            'statuses_id' => $statusId,

            // コントローラーが userId を参照している実装なら必要
            'userId' => $user->id,

            'go' => '09:00',
            'back' => '18:00',

            // 既存休憩（配列）
            'rest_ids' => [$restId],
            'start' => '13:00',
            'end' => '14:00',

            // 追加休憩（空でOK）
            'new_start' => '',
            'new_end' => '',

            // note 必須
            'note' => '休憩時間を1時間遅くした',
        ];

        $update = $this->post('/attendance/update', $payload);

        $this->post('/logout')->assertStatus(302);
        // 3) 管理者ユーザーでログインし、承認画面/申請一覧画面を確認
        // ---------------------------
        $adminPass = 'AdminPass123!';
        $admin = User::create([
            'name' => '管理者花子',
            'email' => 'admin@example.com',
            'password' => Hash::make($adminPass),
            'admin' => 1,
        ]);
        $admin->forceFill(['email_verified_at' => $now->toDateTimeString()])->save();

        // 管理者ログイン（あなたのルートに合わせて）
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => $adminPass,
        ])->assertStatus(302);

        $this->assertAuthenticatedAs($admin);

        // ---- (A) 承認待ち（apply=1）画面を確認 ----
        // ここは管理者側の「承認画面」URLに合わせてください
        $approval = $this->get('/stamp_correction_request/list');
        $approval->assertStatus(200);

        // 申請が一覧に出ている確認（名前/備考など）
        $approval->assertSee('一般ユーザー太郎');

        Carbon::setTestNow(); // 解除
    }
}
