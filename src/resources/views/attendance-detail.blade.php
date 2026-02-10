@extends('layouts.logined')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('content')
        <div class="container">
            <h1 class="container__title">勤怠詳細</h1>
            <form action="/attendance/update" method="POST">
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
                                @if($apply != 1)
                                    <input type="time" name="go" class="row__data--time" value="{{ old("go", $goTime) }}" />
                                @else
                                    <input type="hidden" name="go" value="{{ $goTime }}" />
                                    {{ $goTime }}
                                @endif
                            </div>
                            <div class="row__data--center">～</div>
                            <div class="row__data--right">
                                @if($apply != 1)
                                    <input type="time" name="back" class="row__data--time" value="{{ old("back", $backTime) }}" />
                                @else
                                    <input type="hidden" name="back" value="{{ old("back", $backTime) }}" />
                                    {{ $backTime }}
                                @endif
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
                                    @if($apply != 1)
                                        <input type="time" name="start[]" class="row__data--time"
                                            value="{{ old("start.$i", $rest['start']) }}" />
                                    @else
                                        <input type="hidden" name="start[]" value="{{ $rest['start'] }}">
                                        {{ $rest['start'] }}
                                    @endif
                                </div>

                                <div class="row__data--center">～</div>

                                <div class="row__data--right">
                                    @if($apply != 1)
                                        <input type="time" name="end[]" class="row__data--time"
                                            value="{{ old("end.$i", $rest['end']) }}" />
                                    @else
                                        <input type="hidden" name="end[]" value="{{ $rest['end'] }}">
                                        {{ $rest['end'] }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @php
    $nextNum = count($rests) + 1; // 既存が0件なら1になる
    $addLabel = ($nextNum === 1) ? '休憩' : '休憩' . $nextNum;
                    @endphp

                    @if($apply != 1)
                        <div class="row">
                            <div class="row__name">{{ $addLabel }}</div>

                            <div class="row__data">
                                <div class="row__data--left">
                                    <input type="time" name="new_start" class="row__data--time" value="{{ old('new_start') }}" />
                                    @error('new_start')
                                        <p class="error-text">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="row__data--center">～</div>

                                <div class="row__data--right">
                                    <input type="time" name="new_end" class="row__data--time" value="{{ old('new_end') }}" />
                                    @error('new_end')
                                        <p class="error-text">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="row">
                        <div class="row__name">備考</div>
                        <div class="row__data">
                            @if($apply != 1)
                                <input type="textarea" class="row__data--textarea" name="note" value="{{ old('note', $note) }}">
                            @else
                                {{ $note }}
                            @endif

                        </div>
                    </div>
                </div>
                <div class="container__button">
                    <input type="hidden" name="userId" value="{{ $userId }}">
                    <input type="hidden" name="statuses_id" value="{{ $id }}">
                    @if($apply != 1)
                        <button type="submit">修正</button>
                    @else
                        <p class="container__button--red">*承認待ちのため修正はできません。</p>
                    @endif
                </div>
            </form>
        </div>
@endsection