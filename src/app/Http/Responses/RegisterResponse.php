<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Illuminate\Http\Request;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        // 登録後は必ず認証誘導へ
        return redirect('/email/veify');
    }
}