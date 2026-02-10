<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // 未ログインならログインへ
        if (!auth()->check()) {
            return redirect('/admin/login');
        }

        // admin判定（usersテーブルの admin カラムを利用）
        $user = auth()->user();
        if ((int) $user->admin !== 1) {
            abort(403, '管理者のみアクセスできます。');
        }

        return $next($request);
    }
}
