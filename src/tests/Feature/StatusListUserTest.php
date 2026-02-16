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
use Illuminate\Support\Facades\DB;
class StatusListUserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_shows_all_my_attendance_records_on_list_page()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 今日を固定（一覧の表示月決定や「今日」の処理がブレないように）
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 1) 勤怠情報が登録されたユーザーを作成（認証済みにする）
        $plainPassword = 'Password123!';

        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);

        // email_verified_at が create() で入らないケース対策（確実に入れる）
        $user->forceFill([
            'email_verified_at' => $now->toDateTimeString(),
        ])->save();

        // 3日分の勤怠を作成（同じユーザー）
        $dates = [
            Carbon::create(2026, 2, 14, 9, 0, 0, 'Asia/Tokyo'),
            Carbon::create(2026, 2, 15, 9, 0, 0, 'Asia/Tokyo'),
            Carbon::create(2026, 2, 16, 9, 0, 0, 'Asia/Tokyo'),
        ];

        foreach ($dates as $d) {
            DB::table('statuses')->insert([
                'user_id' => $user->id,
                'status' => 3, // 勤務外/退勤済み等。表示条件に依存しないなら何でもOK
                'go' => $d->toDateTimeString(),
                'rest' => 3600, // 秒（例：1時間）
                'back' => $d->copy()->setTime(18, 0, 0)->toDateTimeString(),
                'sum' => 8 * 3600, // 秒（例：8時間）
                'apply' => 0,
                'applied_at' => null,
                'note' => null,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]);
        }

        // 作成した3件のIDを取得（一覧で表示される詳細リンク確認に使う）
        $statusIds = DB::table('statuses')
            ->where('user_id', $user->id)
            ->orderBy('go', 'asc')
            ->pluck('id')
            ->all();

        $this->assertCount(3, $statusIds);

        // 1. 勤怠情報が登録されたユーザーにログインする（コントローラー経由）
        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => $plainPassword,
        ]);

        $login->assertStatus(302);
        $login->assertRedirect('/attendance');

        $this->assertAuthenticatedAs($user);

        // 2. 勤怠一覧ページを開く
        $list = $this->get('/attendance/list');

        $list->assertStatus(200);

        $jpWeek = ['日', '月', '火', '水', '木', '金', '土'];

        foreach ($dates as $d) {
            $w = $jpWeek[$d->dayOfWeek];
            $expected = $d->format('m/d') . "({$w})"; // 例: 02/16(月)

            $list->assertSee($expected);
        }

        Carbon::setTestNow(); // 解除
    }

    public function test_now()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 「今日」を固定（テストが日付に依存しないように）
        $now = Carbon::create(2026, 2, 16, 19, 0, 0, 'Asia/Tokyo'); // 19:00
        Carbon::setTestNow($now);

        // ユーザー作成＆ログイン
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'admin' => 0,
            'email_verified_at' => $now->toDateTimeString(),
        ]);

        // ログイン
        $response = $this->followingRedirects()->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $attendanceList = $this->get('/attendance/list');
        $attendanceList->assertSee('2026/02');
    }

    public function test_prev()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 「今日」を固定（テストが日付に依存しないように）
        $now = Carbon::create(2026, 2, 16, 19, 0, 0, 'Asia/Tokyo'); // 19:00
        Carbon::setTestNow($now);

        // ユーザー作成＆ログイン
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'admin' => 0,
            'email_verified_at' => $now->toDateTimeString(),
        ]);

        // 3日分の勤怠を作成（同じユーザー）
        $dates = [
            Carbon::create(2026, 1, 14, 9, 0, 0, 'Asia/Tokyo'),
            Carbon::create(2026, 2, 15, 9, 0, 0, 'Asia/Tokyo'),
            Carbon::create(2026, 2, 16, 9, 0, 0, 'Asia/Tokyo'),
        ];

        foreach ($dates as $d) {
            DB::table('statuses')->insert([
                'user_id' => $user->id,
                'status' => 3, // 勤務外/退勤済み等。表示条件に依存しないなら何でもOK
                'go' => $d->toDateTimeString(),
                'rest' => 3600, // 秒（例：1時間）
                'back' => $d->copy()->setTime(18, 0, 0)->toDateTimeString(),
                'sum' => 8 * 3600, // 秒（例：8時間）
                'apply' => 0,
                'applied_at' => null,
                'note' => null,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]);
        }

        // ログイン
        $response = $this->followingRedirects()->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $attendanceList = $this->get('/attendance/list');
        $response = $this->followingRedirects()->post('/attendance/list/move', [
            'direction' => 'prev',
        ]);

        $response->assertSee('2026/01');
    }

    public function test_next()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 「今日」を固定（テストが日付に依存しないように）
        $now = Carbon::create(2026, 2, 16, 19, 0, 0, 'Asia/Tokyo'); // 19:00
        Carbon::setTestNow($now);

        // ユーザー作成＆ログイン
        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make('Password123!'),
            'admin' => 0,
            'email_verified_at' => $now->toDateTimeString(),
        ]);

        // 3日分の勤怠を作成（同じユーザー）
        $dates = [
            Carbon::create(2026, 1, 14, 9, 0, 0, 'Asia/Tokyo'),
            Carbon::create(2026, 2, 15, 9, 0, 0, 'Asia/Tokyo'),
            Carbon::create(2026, 3, 16, 9, 0, 0, 'Asia/Tokyo'),
        ];

        foreach ($dates as $d) {
            DB::table('statuses')->insert([
                'user_id' => $user->id,
                'status' => 3, // 勤務外/退勤済み等。表示条件に依存しないなら何でもOK
                'go' => $d->toDateTimeString(),
                'rest' => 3600, // 秒（例：1時間）
                'back' => $d->copy()->setTime(18, 0, 0)->toDateTimeString(),
                'sum' => 8 * 3600, // 秒（例：8時間）
                'apply' => 0,
                'applied_at' => null,
                'note' => null,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]);
        }

        // ログイン
        $response = $this->followingRedirects()->post('/login', [
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ]);

        $attendanceList = $this->get('/attendance/list');
        $response = $this->followingRedirects()->post('/attendance/list/move', [
            'direction' => 'next',
        ]);

        $response->assertSee('2026/03');
    }

    public function test_detail()
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        // 日付を固定（曜日表示がブレないように）
        $now = Carbon::create(2026, 2, 16, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($now);

        // 1) ユーザー作成
        $plainPassword = 'Password123!';

        $user = User::create([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => Hash::make($plainPassword),
            'admin' => 0,
        ]);

        // email_verified_at が create() で入らない対策（確実に認証済みにする）
        $user->forceFill([
            'email_verified_at' => $now->toDateTimeString(),
        ])->save();

        // 2) statuses を1件作成（今日 09:00〜18:00、備考あり）
        $go = Carbon::create(2026, 2, 16, 9, 0, 0, 'Asia/Tokyo');
        $back = Carbon::create(2026, 2, 16, 18, 0, 0, 'Asia/Tokyo');

        $statusId = DB::table('statuses')->insertGetId([
            'user_id' => $user->id,
            'status' => 3,
            'go' => $go->toDateTimeString(),
            'rest' => 3600, // 例：1時間
            'back' => $back->toDateTimeString(),
            'sum' => 8 * 3600, // 例：8時間
            'apply' => 0,
            'applied_at' => null,
            'note' => null,
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

        // 4) /attendance/detail/{id} を開く
        $response = $this->get("/attendance/detail/{$statusId}");
        $response->assertStatus(200);

        // 5) 画面表示の確認（statuses の情報が見えているか）
        $response->assertSee('勤怠詳細');
        $response->assertSee('テスト太郎');

        // 出勤・退勤
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        Carbon::setTestNow(); // 解除
    }
}
