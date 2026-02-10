@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('content')
    <div class="container">
        <h1 class="container__title">勤怠詳細</h1>
        <form action="{{ route('admin.apply')}}" method="POST">
            @csrf
            @if($errors->any())
                <div class="error-box">
                    <ul>
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="container__content">
                <div class="row">
                    <div class="row__name">名前</div>
                    <div class="row__data">
                        <span class="row__data--name">{{ $name }}</span>
                    </div>
                </div>
                <div class="row">
                    <div class="row__name">日付</div>
                    <div class="row__data">
                        <div class="row__data--left">{{ $yearLabel }}</div>
                        <div class="row__data--right">{{ $dateLabel }}</div>
                    </div>
                </div>
                <div class="row">
                    <div class="row__name">出勤・退勤</div>
                    <div class="row__data">
                        <div class="row__data--left">
                            <input type="hidden" name="go" value="{{ $goTime }}" />
                            {{ $goTime }}
                        </div>
                        <div class="row__data--center">～</div>
                        <div class="row__data--right">
                            <input type="hidden" name="back" value="{{ old("back", $backTime) }}" />
                            {{ $backTime }}
                        </div>
                    </div>
                </div>
                @foreach($rests as $rest)
                    @php
                        $n = $loop->iteration; // 1,2,3...
                        $label = $n === 1 ? '休憩' : '休憩' . $n;
                        $i = $loop->index; // 0,1,2...（old() / error表示用）
                    @endphp
                    <div class="row">
                        <div class="row__name">{{ $label }}</div>

                        <div class="row__data">
                            <input type="hidden" name="rest_ids[]" value="{{ $rest['id'] }}">
                            <div class="row__data--left">
                                <input type="hidden" name="start[]" value="{{ $rest['start'] }}">
                                {{ $rest['start'] }}
                            </div>

                            <div class="row__data--center">～</div>

                            <div class="row__data--right">
                                <input type="hidden" name="end[]" value="{{ $rest['end'] }}">
                                {{ $rest['end'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
                @if($restAdd == 0)
                    @php
                        $nextNum = count($rests) + 1; // 既存が0件なら1になる
                        $addLabel = ($nextNum === 1) ? '休憩' : '休憩' . $nextNum;
                    @endphp
                    @if($restAdd == 0)
                        <div class="row">
                            <div class="row__name">{{ $addLabel }}</div>

                            <div class="row__data">
                                <div class="row__data--left">
                                    <input type="hidden" name="new_start" class="row__data--time" value="{{ old('new_start') }}" />
                                </div>
                                <div class="row__data--center">～</div>
                                <div class="row__data--right">
                                    <input type="hidden" name="new_end" class="row__data--time" value="{{ old('new_end') }}" />
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
                <div class="row">
                    <div class="row__name">備考</div>
                    <div class="row__data">
                        <input type="hidden" name="note" value="{{  $note }}">
                        {{ $note }}
                    </div>
                </div>
            </div>
            @if($apply == 2)
                <div class="container__applied">
                    承認済み
                </div>
            @else
                <div class="container__button">
                    <input type="hidden" name="statuses_id" value="{{ $id }}">
                    <button type="submit">承認</button>
                </div>
            @endif
        </form>
    </div>
@endsection