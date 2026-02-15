<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_name_required()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $response = $this->from('/register')->post('/register', [
            'name' => '', // 未入力
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // バリデーションで /register に戻る想定
        $response->assertRedirect('/register');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    public function test_email_required()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎', // 未入力
            'email' => '',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // バリデーションで /register に戻る想定
        $response->assertRedirect('/register');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }
    public function test_passoword_min()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎', // 未入力
            'email' => 'z@z',
            'password' => 'Pass',
            'password_confirmation' => 'Pass',
        ]);

        // バリデーションで /register に戻る想定
        $response->assertRedirect('/register');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    public function test_passoword_equal()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎', // 未入力
            'email' => 'z@z',
            'password' => 'Password',
            'password_confirmation' => 'Password!',
        ]);

        // バリデーションで /register に戻る想定
        $response->assertRedirect('/register');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    public function test_passoword_required()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎', // 未入力
            'email' => 'z@z',
            'password' => '',
            'password_confirmation' => '',
        ]);

        // バリデーションで /register に戻る想定
        $response->assertRedirect('/register');

        // name のエラーメッセージが指定どおりか
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function test_regist_success()
    {
        // name だけ未入力にして、他の必須項目は正しい値を入れる
        $response = $this->from('/register')->post('/register', [
            'name' => '山田太郎', // 未入力
            'email' => 'z@z',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        // ユーザーが作られたこと
        $this->assertDatabaseHas('users', [
            'email' => 'z@z',
            'name' => '山田太郎',
        ]);
    }
}
