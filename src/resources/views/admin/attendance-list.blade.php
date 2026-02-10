@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance-list.css') }}">
@endsection

@section('content')
    <div class="container">
        <h1 class="container__title">{{ $day->format('Y年n月j日') }}の勤怠</h1>
        {{-- ===== 上部：月ページャ（URLは常に /attendance/list のまま）===== --}}
        <div class="container__paginate">

            {{-- 前月（古い月へ） --}}
            <div class="container__prev">
                @if(!empty($prevYmd))
                    <form action="/admin/attendance/list/move" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="direction" value="prev">
                        <button type="submit">
                            <span class="arrow arrow--left" aria-hidden="true"></span>
                            前日
                        </button>
                    </form>
                @else
                    <span class="container__prev--span">
                        <span class="arrow arrow--left" aria-hidden="true"></span>
                        前日
                    </span>
                @endif
            </div>

            {{-- 現在表示中の年月 --}}
            <div class="container__this">
                <img class="container__calendar" src="/images/calendar.png" alt="coachtech">
                {{ $day->format('Y/m/d') }} {{-- 例: 2026/01 --}}
            </div>

            {{-- 翌月（新しい月へ） --}}
            <div class="container__next">
                @if(!empty($nextYmd))
                    <form action="/admin/attendance/list/move" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="direction" value="next">
                        <button type="submit">
                            翌日
                            <span class="arrow arrow--right" aria-hidden="true"></span>
                        </button>
                    </form>
                @else
                    <span class="container__next--span">
                        翌日
                        <span class="arrow arrow--right" aria-hidden="true"></span>
                    </span>
                @endif
            </div>
        </div>

        {{-- ===== テーブル：表示中の月の勤怠 ===== --}}
        <div>
            <table class="container__table">
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($items as $row)
                        <tr>
                            <td>{{ $row->user->name }}</td>
                            <td>{{ $row->date_label }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->go)->format('H:i') }}
                            </td>
                            <td>{{ $row->back ? \Carbon\Carbon::parse($row->back)->format('H:i') : '-' }}</td>
                            <td>{{ gmdate('G:i', (int) $row->rest) }}</td>
                            <td>{{ gmdate('G:i', (int) $row->sum) }}</td>
                            <td><a href="/admin/attendance/detail/{{ $row->id }}">詳細</a></td>
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