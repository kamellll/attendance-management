<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
class AuthController extends Controller
{
    public function index()
    {
        return view('/auth/login');
    }
    public function registerView()
    {
        return view('/auth/register');
    }
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        // remember me も使うなら第2引数に渡す
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {

            // ここで「未登録 or 不一致」のエラーメッセージを付けて元の画面に戻す
            return back()->withErrors([
                'email' => 'ログイン情報が登録されていません',
            ])->onlyInput('email'); // email だけ old() で保持
        }

        // ログイン成功時はセッション再生成
        $request->session()->regenerate();

        // ★ ここで users テーブルの is_profile_completed をチェック
        $user = $request->user(); // Auth::user() でもOK
        if ($user->email_verified_at == NULL) {
            // まだメール未認証　→　認証メール送信
            return redirect('/email/verify');
        }
        return redirect('/');
    }
    public function store(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('verification.notice');
    }
    public function email(Request $request)
    {
        $user = Auth::user();
        event(new Registered($user));
        return view('verify-email');
    }
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login'); // 好きな画面へ
    }
}
