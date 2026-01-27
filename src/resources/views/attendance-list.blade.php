@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance-list.css') }}">
@endsection

@section('content')
    <div class="container">

        {{-- ===== 上部：月ページャ（URLは常に /attendance/list のまま）===== --}}
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;">

            {{-- 前月（古い月へ） --}}
            <div style="flex:1; text-align:left;">
                @if(!empty($prevYm))
                    <form action="{{ route('attendance.list.move') }}" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="direction" value="prev">
                        <button type="submit">前月</button>
                    </form>
                @else
                    <span style="color:#999;">前月</span>
                @endif
            </div>

            {{-- 現在表示中の年月 --}}
            <div style="flex:1; text-align:center; font-weight:700;">
                {{ $monthLabel }} {{-- 例: 2026/01 --}}
            </div>

            {{-- 翌月（新しい月へ） --}}
            <div style="flex:1; text-align:right;">
                @if(!empty($nextYm))
                    <form action="{{ route('attendance.list.move') }}" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="direction" value="next">
                        <button type="submit">翌月</button>
                    </form>
                @else
                    <span style="color:#999;">翌月</span>
                @endif
            </div>
        </div>

        {{-- ===== テーブル：表示中の月の勤怠 ===== --}}
        <div style="overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="border-bottom:1px solid #ddd; padding:10px; text-align:left;">日付</th>
                        <th style="border-bottom:1px solid #ddd; padding:10px; text-align:left;">出勤</th>
                        <th style="border-bottom:1px solid #ddd; padding:10px; text-align:left;">退勤</th>
                        <th style="border-bottom:1px solid #ddd; padding:10px; text-align:right;">休憩合計</th>
                        <th style="border-bottom:1px solid #ddd; padding:10px; text-align:right;">勤務合計</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($items as $row)
                        <tr>
                            <td style="border-bottom:1px solid #eee; padding:10px;">
                                {{ $row->date_label }} {{-- 2026/01/26(月) --}}
                            </td>

                            <td style="border-bottom:1px solid #eee; padding:10px;">
                                {{ \Carbon\Carbon::parse($row->go)->format('H:i') }}
                            </td>

                            <td style="border-bottom:1px solid #eee; padding:10px;">
                                {{ $row->back ? \Carbon\Carbon::parse($row->back)->format('H:i') : '-' }}
                            </td>

                            <td style="border-bottom:1px solid #eee; padding:10px; text-align:right;">
                                {{ gmdate('H:i', (int) $row->rest) }}
                            </td>

                            <td style="border-bottom:1px solid #eee; padding:10px; text-align:right;">
                                {{ gmdate('H:i', (int) $row->sum) }}
                            </td>
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