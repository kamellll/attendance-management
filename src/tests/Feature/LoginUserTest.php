<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginUserTest extends TestCase
{
    use RefreshDatabase;
    public function test_email_required()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $user = User::create([
            'name' => '山田　太郎',
            'email' => 'yamada1234@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        // バリデーションで /login に戻る想定
        $response->assertRedirect('/login');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    public function test_password_required()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $user = User::create([
            'name' => '山田　太郎',
            'email' => 'yamada1234@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'yamada1234@example.com',
            'password' => '',
        ]);

        // バリデーションで /register に戻る想定
        $response->assertRedirect('/login');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function test_not_register()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $user = User::create([
            'name' => '山田　太郎',
            'email' => 'yamada1234@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'yamada1234@example.com',
            'password' => 'aaa',
        ]);

        // バリデーションで /register に戻る想定
        $response->assertRedirect('/login');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
