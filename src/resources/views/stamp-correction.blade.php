@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/stamp-correction.css') }}">
@endsection

@section('content')
    <div class="container">
        <h1 class="container__title">申請一覧</h1>
        <div class="container__tab">
            <form action="{{ route('stamp_correction.filter') }}" method="POST">
                @csrf
                <button type="submit" name="wait"
                    class="container__tab--button {{ $applyFilter == 1 ? 'container__tab--selected' : '' }}"> 承認待ち
                </button>
                <button type="submit" name="applied" class="container__tab--button {{ $applyFilter == 2 ? 'container__tab--selected' : '' }}
                                ">承認済み</button>
            </form>
        </div>

        {{-- ===== テーブル：表示中の月の勤怠 ===== --}}
        <div>
            <table class="container__table">
                <thead>
                    <tr>
                        <th>状態</th>
                        <th>名前</th>
                        <th>対象日時</th>
                        <th>申請理由</th>
                        <th>申請日時</th>
                        <th>詳細</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($items as $row)
                        <tr>
                            <td>
                                @if($row->apply == 1)
                                    承認待ち
                                @elseif($row->apply == 2)
                                    承認済み
                                @else
                                    未申請
                                @endif
                            </td>
                            <td>{{ $row->user->name }}
                            </td>
                            <td>{{ $row->go ? \Carbon\Carbon::parse($row->go)->format('Y/m/d') : '-' }}</td>
                            <td>{{ $row->note }}</td>
                            <td>{{ $row->applied_at ? \Carbon\Carbon::parse($row->applied_at)->format('Y/m/d') : '-' }}</td>
                            @if(Auth::check() && Auth::user()->isAdmin())
                                <td><a href="/stamp_correction_request/approve/{{ $row->id }}">詳細</a></td>
                            @else
                                <td><a href="/attendance/detail/{{ $row->id }}">詳細</a></td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:14px; color:#666;">
                                申請データはありません。
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection