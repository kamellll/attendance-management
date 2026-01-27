@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance-list.css') }}">
@endsection

@section('content')
    <div class="container">

        {{-- ===== 上部：月ページャ（前月 / 年月 / 翌月）===== --}}
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;">
            <div style="flex:1; text-align:left;">
                @if(!empty($prevYm))
                    <a href="{{ url('/attendance/list') }}?month={{ $prevYm }}">前月</a>
                @else
                    <span style="color:#999;">前月</span>
                @endif
            </div>

            <div style="flex:1; text-align:center; font-weight:700;">
                {{ $monthLabel }} {{-- 例: 2026/01 --}}
            </div>

            <div style="flex:1; text-align:right;">
                @if(!empty($nextYm))
                    <a href="{{ url('/attendance/list') }}?month={{ $nextYm }}">翌月</a>
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
                            {{-- 日付：2026/01/26(月) --}}
                            <td style="border-bottom:1px solid #eee; padding:10px;">
                                {{ $row->date_label }}
                            </td>

                            {{-- 出勤：09:00 --}}
                            <td style="border-bottom:1px solid #eee; padding:10px;">
                                {{ \Carbon\Carbon::parse($row->go)->format('H:i') }}
                            </td>

                            {{-- 退勤：18:00 or - --}}
                            <td style="border-bottom:1px solid #eee; padding:10px;">
                                {{ $row->back ? \Carbon\Carbon::parse($row->back)->format('H:i') : '-' }}
                            </td>

                            {{-- 休憩合計：01:00（秒→HH:MM） --}}
                            <td style="border-bottom:1px solid #eee; padding:10px; text-align:right;">
                                {{ gmdate('H:i', (int) $row->rest) }}
                            </td>

                            {{-- 勤務合計：08:00（秒→HH:MM） --}}
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