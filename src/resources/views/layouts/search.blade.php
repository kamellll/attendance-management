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
    <link rel="stylesheet" href="{{ asset('css/search.css') }}">
    @yield('css')
    @yield('js')
</head>

<body>
    <header class="header">
        <div class="header__inner">
            <a href="/"><img src="/images/rogo.png" alt="coachtech"></a>
            <form action="/" class="header__search" method="post">
                @csrf
                <input type="text" class="header__search-text" name="keyword" placeholder="なにをお探しですか？"
                    value="{{ old('keyword', $keyword ?? '') }}">
                <input type="submit" hidden>
            </form>
            <div class="header__item">
                @if (Auth::check())
                    <form class="header__logout" action="{{ route('logout') }}" method="post">
                        @csrf
                        <button>ログアウト</button>
                    </form>
                @else
                    <form class="header__logout" action="/login" method="get">
                        @csrf
                        <button>ログイン</button>
                    </form>
                @endif
                <a href="/mypage?page=sell" class="header__mypage">マイページ</a>
                <a href="/sell" class="header__sell">出品</a>
            </div>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
</body>

</html>