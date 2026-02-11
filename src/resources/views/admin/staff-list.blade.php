@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance-list.css') }}">
@endsection

@section('content')
    <div class="container">
        <h1 class="container__title">スタッフ一覧</h1>
        {{-- ===== テーブル：表示中の月の勤怠 ===== --}}
        <div>
            <table class="container__table">
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>メールアドレス</th>
                        <th>月次勤怠</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><a href="/admin/attendance/staff/{{ $user->id }}">詳細</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:14px; color:#666;">
                                この月の勤怠データはありません。
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection