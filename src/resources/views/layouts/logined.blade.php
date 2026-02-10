<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COACHTECH</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/logined.css') }}">
    @yield('css')
    @yield('js')
</head>

<body>
    <header class="header">
        <div class="header__inner">
            @if(Auth::check() && Auth::user()->isAdmin())
                <a href="/admin/attendance/list"><img src="/images/rogo.png" alt="coachtech"></a>
            @else
                <a href="/attendance"><img src="/images/rogo.png" alt="coachtech"></a>
            @endif
            <div class="header__item">
                @if(Auth::check() && Auth::user()->isAdmin())
                    {{-- 管理者用メニュー --}}
                    <a href="/admin/attendance/list" class="header__mypage">勤怠一覧</a>
                    <a href="/admin/staff/list" class="header__mypage">スタッフ一覧</a>
                    <a href="/stamp_correction_request/list" class="header__mypage">申請一覧</a>
                    <form class="header__logout" action="{{ route('adminLogout') }}" method="post">
                        @csrf
                        <button>ログアウト</button>
                    </form>
                @else
                    {{-- 一般ユーザー用メニュー --}}
                    <a href="/attendance" class="header__mypage">勤怠</a>
                    <a href="/attendance/list" class="header__mypage">勤怠一覧</a>
                    <a href="/stamp_correction_request/list" class="header__mypage">申請</a>
                    <form class="header__logout" action="{{ route('logout') }}" method="post">
                        @csrf
                        <button>ログアウト</button>
                    </form>
                @endif

            </div>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
</body>

</html>